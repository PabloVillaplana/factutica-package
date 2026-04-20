<?php

namespace FactuTica\FactuticaCR\Services\Hacienda;

use DOMDocument;
use DOMXPath;
use FactuTica\FactuticaCR\Exceptions\XmlSignerException;

/**
 * Firma un XML de comprobante electrónico para el Ministerio de Hacienda
 * usando el estándar XAdES-EPES con empaquetado ENVELOPED.
 *
 * Basado en: ANEXO 2 - Mecanismo de Seguridad v4.4
 * Algoritmos: RSA-SHA256, digest SHA-256, canonicalización xml-exc-c14n
 */
class XmlSignerService
{
    /**
     * URL de la política de firma requerida por Hacienda (no cambia).
     */
    private const POLICY_URL = 'https://cdn.comprobanteselectronicos.go.cr/xml-schemas/Resoluci%C3%B3n_General_sobre_disposiciones_t%C3%A9cnicas_comprobantes_electr%C3%B3nicos_para_efectos_tributarios.pdf';

    /**
     * SHA-256 del PDF de la política (valor fijo publicado por Hacienda).
     */
    private const POLICY_DIGEST = 'DWxin1xWOeI8OuWQXazh4VjLWAaCLAA954em7DMh0h8=';

    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    private const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';

    public function __construct(
        private readonly CertificateLoaderService $certificateLoader,
    ) {}

    /**
     * Firma el XML y retorna el XML completo con el nodo ds:Signature incrustado.
     *
     * @param  string  $xmlString  XML del comprobante sin firmar
     * @param  string|null  $claimedRole  Rol del firmante: null (emisor), 'Receptor',
     *                                    'Endosante1', 'Endosatario1', etc.
     *
     * @throws XmlSignerException
     */
    public function sign(string $xmlString, ?string $claimedRole = null): string
    {
        try {
            $this->certificateLoader->load();
        } catch (\Throwable $e) {
            throw new XmlSignerException("Error cargando certificado para firma: {$e->getMessage()}", 0, $e);
        }

        // 1. Cargar el XML en un DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (! $dom->loadXML($xmlString)) {
            throw new XmlSignerException('El XML proporcionado no es válido.');
        }

        // 2. Generar IDs únicos para esta firma
        $signatureId = 'id-'.md5(uniqid('', true));
        $xadesId = 'xades-'.$signatureId;
        $referenceId = 'r-id-1';
        $valueId = 'value-'.$signatureId;

        // 3. Canonicalizar el documento completo (excluyendo ds:Signature)
        $canonicalXml = $this->canonicalizeDocument($dom);

        // 4. Calcular el DigestValue del documento
        $documentDigest = base64_encode(hash('sha256', $canonicalXml, true));

        // 5. Construir el bloque XAdES SignedProperties
        $signingTime = gmdate('Y-m-d\TH:i:s\Z');
        $signedPropsXml = $this->buildSignedProperties(
            $xadesId, $signatureId, $referenceId, $signingTime, $claimedRole
        );

        // 6. Canonicalizar SignedProperties y calcular su digest
        $spDoc = new DOMDocument('1.0', 'UTF-8');
        $spDoc->preserveWhiteSpace = true;
        $spDoc->formatOutput = false;
        $spDoc->loadXML($signedPropsXml);
        $spXPath = new DOMXPath($spDoc);
        $spXPath->registerNamespace('xades', self::NS_XADES);
        $signedPropsNode = $spXPath->query('//xades:SignedProperties')->item(0);

        if (! $signedPropsNode) {
            throw new XmlSignerException('No se encontró el nodo SignedProperties en el XML construido.');
        }

        $canonicalSignedProps = $signedPropsNode->C14N(true, false);
        $signedPropsDigest = base64_encode(hash('sha256', $canonicalSignedProps, true));

        // 7. Construir el bloque SignedInfo
        $signedInfoXml = $this->buildSignedInfo(
            $referenceId, $documentDigest, $xadesId, $signedPropsDigest
        );

        // 8. Canonicalizar SignedInfo y FIRMAR con la llave privada
        $canonicalSignedInfo = $this->canonicalizeFragment($signedInfoXml);

        $privateKey = openssl_pkey_get_private($this->certificateLoader->getPrivateKey());

        if (! $privateKey) {
            throw new XmlSignerException('No se pudo cargar la llave privada del certificado.');
        }

        $signatureRaw = '';

        if (! openssl_sign($canonicalSignedInfo, $signatureRaw, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new XmlSignerException('Error al firmar: '.openssl_error_string());
        }

        $signatureValue = base64_encode($signatureRaw);

        // 9. Ensamblar el nodo ds:Signature completo
        $signatureXml = $this->buildSignatureNode(
            $signatureId, $valueId, $signedInfoXml, $signatureValue, $signedPropsXml
        );

        // 10. Insertar ds:Signature en el XML del comprobante
        return $this->insertSignatureIntoXml($dom, $signatureXml);
    }

    /**
     * Canonicaliza el documento completo usando xml-exc-c14n,
     * excluyendo nodos ds:Signature existentes.
     */
    private function canonicalizeDocument(DOMDocument $dom): string
    {
        $root = $dom->documentElement;
        $result = $root->C14N(true, false, null, ['ds:Signature']);

        // Si hay firmas existentes (contra-firmas), removerlas del resultado
        if (str_contains($result, '<ds:Signature')) {
            $result = $this->removeSignatureNodes($result);
        }

        return $result;
    }

    /**
     * Canonicaliza un fragmento XML (string) usando xml-exc-c14n.
     */
    private function canonicalizeFragment(string $xmlFragment): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = false;
        $doc->loadXML($xmlFragment);

        return $doc->documentElement->C14N(true, false);
    }

    /**
     * Remueve nodos <ds:Signature> de un XML canonicalizado.
     * Necesario cuando se agregan contra-firmas (Receptor, Endosante).
     */
    private function removeSignatureNodes(string $xml): string
    {
        return preg_replace('/<ds:Signature[\s\S]*?<\/ds:Signature>/m', '', $xml);
    }

    /**
     * Construye el bloque <xades:SignedProperties> con metadatos XAdES.
     * Este es el core de XAdES-EPES.
     */
    private function buildSignedProperties(
        string $xadesId,
        string $signatureId,
        string $referenceId,
        string $signingTime,
        ?string $claimedRole,
    ): string {
        $issuerSerial = $this->certificateLoader->getIssuerSerial();
        $certDigest = $this->certificateLoader->getCertificateDigestSha1();

        $signerRoleBlock = '';

        if ($claimedRole !== null) {
            $signerRoleBlock = "
                <xades:SignerRole>
                    <xades:ClaimedRoles>
                        <xades:ClaimedRole>{$claimedRole}</xades:ClaimedRole>
                    </xades:ClaimedRoles>
                </xades:SignerRole>";
        }

        $issuerName = htmlspecialchars($issuerSerial['issuer'], ENT_XML1);
        $serialNumber = htmlspecialchars($issuerSerial['serial'], ENT_XML1);
        $policyUrl = htmlspecialchars(self::POLICY_URL, ENT_XML1);
        $policyDigest = htmlspecialchars(self::POLICY_DIGEST, ENT_XML1);

        return <<<XML
        <xades:QualifyingProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Target="#{$signatureId}">
            <xades:SignedProperties Id="{$xadesId}">
                <xades:SignedSignatureProperties>
                    <xades:SigningTime>{$signingTime}</xades:SigningTime>
                    <xades:SigningCertificate>
                        <xades:Cert>
                            <xades:CertDigest>
                                <ds:DigestMethod xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
                                <ds:DigestValue xmlns:ds="http://www.w3.org/2000/09/xmldsig#">{$certDigest}</ds:DigestValue>
                            </xades:CertDigest>
                            <xades:IssuerSerial>
                                <ds:X509IssuerName xmlns:ds="http://www.w3.org/2000/09/xmldsig#">{$issuerName}</ds:X509IssuerName>
                                <ds:X509SerialNumber xmlns:ds="http://www.w3.org/2000/09/xmldsig#">{$serialNumber}</ds:X509SerialNumber>
                            </xades:IssuerSerial>
                        </xades:Cert>
                    </xades:SigningCertificate>
                    <xades:SignaturePolicyIdentifier>
                        <xades:SignaturePolicyId>
                            <xades:SigPolicyId>
                                <xades:Identifier>{$policyUrl}</xades:Identifier>
                            </xades:SigPolicyId>
                            <xades:SigPolicyHash>
                                <ds:DigestMethod xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                                <ds:DigestValue xmlns:ds="http://www.w3.org/2000/09/xmldsig#">{$policyDigest}</ds:DigestValue>
                            </xades:SigPolicyHash>
                        </xades:SignaturePolicyId>
                    </xades:SignaturePolicyIdentifier>{$signerRoleBlock}
                </xades:SignedSignatureProperties>
                <xades:SignedDataObjectProperties>
                    <xades:DataObjectFormat ObjectReference="#{$referenceId}">
                        <xades:MimeType>application/octet-stream</xades:MimeType>
                    </xades:DataObjectFormat>
                </xades:SignedDataObjectProperties>
            </xades:SignedProperties>
        </xades:QualifyingProperties>
        XML;
    }

    /**
     * Construye el bloque <ds:SignedInfo> con ambas referencias:
     *   - Reference 1: el documento XML completo
     *   - Reference 2: el bloque SignedProperties
     */
    private function buildSignedInfo(
        string $referenceId,
        string $documentDigest,
        string $xadesId,
        string $signedPropsDigest,
    ): string {
        return <<<XML
        <ds:SignedInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
            <ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
            <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
            <ds:Reference Id="{$referenceId}" Type="" URI="">
                <ds:Transforms>
                    <ds:Transform Algorithm="http://www.w3.org/TR/1999/REC-xpath-19991116">
                        <ds:XPath>not(ancestor-or-self::ds:Signature)</ds:XPath>
                    </ds:Transform>
                    <ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
                </ds:Transforms>
                <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                <ds:DigestValue>{$documentDigest}</ds:DigestValue>
            </ds:Reference>
            <ds:Reference Type="http://uri.etsi.org/01903#SignedProperties" URI="#{$xadesId}">
                <ds:Transforms>
                    <ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
                </ds:Transforms>
                <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
                <ds:DigestValue>{$signedPropsDigest}</ds:DigestValue>
            </ds:Reference>
        </ds:SignedInfo>
        XML;
    }

    /**
     * Ensambla el nodo <ds:Signature> completo listo para insertar en el XML.
     */
    private function buildSignatureNode(
        string $signatureId,
        string $valueId,
        string $signedInfoXml,
        string $signatureValue,
        string $signedPropsXml,
    ): string {
        $certBase64 = $this->certificateLoader->getCertificateBase64();
        $signedInfoInner = $this->extractSavedXml($signedInfoXml);

        return <<<XML
        <ds:Signature Id="{$signatureId}" xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
            {$signedInfoInner}
            <ds:SignatureValue Id="{$valueId}">{$signatureValue}</ds:SignatureValue>
            <ds:KeyInfo>
                <ds:X509Data>
                    <ds:X509Certificate>{$certBase64}</ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
            <ds:Object>
                {$signedPropsXml}
            </ds:Object>
        </ds:Signature>
        XML;
    }

    /**
     * Extrae el contenido XML de un elemento para embeber.
     */
    private function extractSavedXml(string $xml): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = false;
        $doc->loadXML($xml);

        return $doc->saveXML($doc->documentElement);
    }

    /**
     * Inserta el nodo ds:Signature como último hijo del elemento raíz.
     *
     * Usa manipulación de strings en lugar de DOMDocument para evitar
     * modificaciones no deseadas que romperían la validación de la firma.
     *
     * @throws XmlSignerException
     */
    private function insertSignatureIntoXml(DOMDocument $dom, string $signatureXml): string
    {
        $result = $dom->saveXML();

        if ($result === false) {
            throw new XmlSignerException('Error al serializar el documento XML.');
        }

        $rootTagName = $dom->documentElement->tagName;
        $closingTag = '</'.$rootTagName.'>';
        $lastClosingPos = strrpos($result, $closingTag);

        if ($lastClosingPos === false) {
            throw new XmlSignerException("No se encontró el tag de cierre del elemento raíz: {$rootTagName}");
        }

        // rtrim() evita nodos de texto extra entre </ds:Signature> y </root>
        // que romperían la verificación del digest del documento
        return substr_replace($result, rtrim($signatureXml), $lastClosingPos, 0);
    }
}