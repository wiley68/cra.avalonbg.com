<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Minimal SAML 2.0 SP helper (HTTP-Redirect AuthnRequest + HTTP-POST ACS).
 * Mirrors the lightweight OidcClient approach — no external SAML package.
 */
class SamlClient
{
    public function newRequestId(): string
    {
        return '_' . Str::uuid()->toString();
    }

    public function newRelayState(): string
    {
        return Str::random(40);
    }

    public function spEntityId(): string
    {
        return rtrim((string) config('app.url'), '/') . '/auth/sso/metadata';
    }

    public function acsUrl(): string
    {
        return route('auth.sso.acs', absolute: true);
    }

    /**
     * Build an IdP redirect URL with a deflated SAMLRequest (Redirect Binding).
     *
     * @return array{url: string, request_id: string, relay_state: string}
     */
    public function beginLogin(
        string $idpSsoUrl,
        string $idpEntityId,
        ?string $spEntityId = null,
        ?string $acsUrl = null,
    ): array {
        $idpSsoUrl = trim($idpSsoUrl);
        $idpEntityId = trim($idpEntityId);

        if ($idpSsoUrl === '' || $idpEntityId === '') {
            throw new RuntimeException('SAML IdP SSO URL and entity ID are required.');
        }

        $spEntityId = $spEntityId ?: $this->spEntityId();
        $acsUrl = $acsUrl ?: $this->acsUrl();
        $requestId = $this->newRequestId();
        $relayState = $this->newRelayState();
        $instant = gmdate('Y-m-d\TH:i:s\Z');

        $authnRequest = <<<XML
<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="{$requestId}"
    Version="2.0"
    IssueInstant="{$instant}"
    Destination="{$this->xmlEscape($idpSsoUrl)}"
    AssertionConsumerServiceURL="{$this->xmlEscape($acsUrl)}"
    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
  <saml:Issuer>{$this->xmlEscape($spEntityId)}</saml:Issuer>
  <samlp:NameIDPolicy Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress" AllowCreate="false"/>
</samlp:AuthnRequest>
XML;

        $deflated = gzdeflate($authnRequest);
        if ($deflated === false) {
            throw new RuntimeException('Failed to compress SAML AuthnRequest.');
        }

        $query = http_build_query([
            'SAMLRequest' => base64_encode($deflated),
            'RelayState' => $relayState,
        ], '', '&', PHP_QUERY_RFC3986);

        $separator = str_contains($idpSsoUrl, '?') ? '&' : '?';

        return [
            'url' => $idpSsoUrl . $separator . $query,
            'request_id' => $requestId,
            'relay_state' => $relayState,
        ];
    }

    /**
     * Parse and validate a base64-encoded SAML Response from HTTP-POST ACS.
     *
     * @return array{email: string, name_id: string|null, in_response_to: string|null}
     */
    public function completeLogin(
        string $samlResponseBase64,
        string $expectedIdpEntityId,
        string $idpX509Cert,
        ?string $expectedInResponseTo = null,
        ?string $expectedSpEntityId = null,
        ?string $expectedAcsUrl = null,
    ): array {
        $xml = base64_decode(preg_replace('/\s+/', '', $samlResponseBase64) ?? '', true);

        if ($xml === false || $xml === '') {
            throw new RuntimeException('Invalid SAMLResponse encoding.');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                throw new RuntimeException('Invalid SAMLResponse XML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $statusCode = $xpath->evaluate('string(//samlp:Status/samlp:StatusCode/@Value)');
        if (
            is_string($statusCode)
            && $statusCode !== ''
            && $statusCode !== 'urn:oasis:names:tc:SAML:2.0:status:Success'
        ) {
            throw new RuntimeException('SAML Response status is not Success.');
        }

        $responseIssuer = trim((string) $xpath->evaluate('string(/samlp:Response/saml:Issuer)'));
        $assertionIssuer = trim((string) $xpath->evaluate('string(//saml:Assertion/saml:Issuer)'));
        $issuer = $assertionIssuer !== '' ? $assertionIssuer : $responseIssuer;

        if ($issuer === '' || !$this->entityIdsMatch($issuer, $expectedIdpEntityId)) {
            throw new RuntimeException('SAML Response issuer mismatch.');
        }

        $expectedAcsUrl ??= $this->acsUrl();
        $destination = trim((string) $xpath->evaluate('string(/samlp:Response/@Destination)'));
        if ($destination !== '' && !$this->urlsMatch($destination, $expectedAcsUrl)) {
            throw new RuntimeException('SAML Response destination mismatch.');
        }

        $expectedSpEntityId ??= $this->spEntityId();
        $audiences = [];
        foreach ($xpath->query('//saml:AudienceRestriction/saml:Audience') ?: [] as $node) {
            $audiences[] = trim($node->textContent);
        }
        if (
            $audiences !== [] && !collect($audiences)->contains(
                fn(string $audience): bool => $this->entityIdsMatch($audience, $expectedSpEntityId),
            )
        ) {
            throw new RuntimeException('SAML Response audience mismatch.');
        }

        $notOnOrAfter = trim((string) $xpath->evaluate('string(//saml:Conditions/@NotOnOrAfter)'));
        if ($notOnOrAfter !== '' && strtotime($notOnOrAfter) !== false && strtotime($notOnOrAfter) < time()) {
            throw new RuntimeException('SAML Assertion has expired.');
        }

        $inResponseTo = trim((string) $xpath->evaluate('string(/samlp:Response/@InResponseTo)'));
        if (
            filled($expectedInResponseTo)
            && $inResponseTo !== ''
            && $inResponseTo !== $expectedInResponseTo
        ) {
            throw new RuntimeException('SAML InResponseTo mismatch.');
        }

        if ((bool) config('sso.saml.require_signature', true)) {
            $this->assertSigned($document, $xpath, $idpX509Cert);
        }

        $email = $this->extractEmail($xpath);
        if ($email === null) {
            throw new RuntimeException('SAML Response did not include an email NameID or attribute.');
        }

        $nameId = trim((string) $xpath->evaluate('string(//saml:Subject/saml:NameID)'));

        return [
            'email' => strtolower($email),
            'name_id' => $nameId !== '' ? $nameId : null,
            'in_response_to' => $inResponseTo !== '' ? $inResponseTo : null,
        ];
    }

    public function metadataXml(?string $spEntityId = null, ?string $acsUrl = null): string
    {
        $spEntityId ??= $this->spEntityId();
        $acsUrl ??= $this->acsUrl();

        return <<<XML
<?xml version="1.0"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
    entityID="{$this->xmlEscape($spEntityId)}">
  <md:SPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol"
      AuthnRequestsSigned="false" WantAssertionsSigned="true">
    <md:NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</md:NameIDFormat>
    <md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
        Location="{$this->xmlEscape($acsUrl)}" index="1" isDefault="true"/>
  </md:SPSSODescriptor>
</md:EntityDescriptor>
XML;
    }

    private function extractEmail(DOMXPath $xpath): ?string
    {
        $nameId = trim((string) $xpath->evaluate('string(//saml:Subject/saml:NameID)'));
        if ($this->looksLikeEmail($nameId)) {
            return strtolower($nameId);
        }

        $attributeNames = [
            'email',
            'mail',
            'Email',
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
        ];

        foreach ($attributeNames as $name) {
            $query = sprintf(
                '//saml:Attribute[@Name=%s]/saml:AttributeValue',
                $this->xpathLiteral($name),
            );
            $value = trim((string) $xpath->evaluate('string(' . $query . ')'));
            if ($this->looksLikeEmail($value)) {
                return strtolower($value);
            }
        }

        return null;
    }

    private function assertSigned(DOMDocument $document, DOMXPath $xpath, string $idpX509Cert): void
    {
        $signatureNodes = $xpath->query('//ds:Signature');
        if ($signatureNodes === false || $signatureNodes->length === 0) {
            throw new RuntimeException('SAML Response is missing an XML signature.');
        }

        $cert = $this->normalizeCertificate($idpX509Cert);
        $publicKey = openssl_pkey_get_public($cert);
        if ($publicKey === false) {
            throw new RuntimeException('Invalid IdP X.509 certificate.');
        }

        /** @var DOMElement $signature */
        $signature = $signatureNodes->item(0);
        $signedInfo = $xpath->query('./ds:SignedInfo', $signature)?->item(0);
        $signatureValueNode = $xpath->query('./ds:SignatureValue', $signature)?->item(0);

        if (!$signedInfo instanceof DOMElement || !$signatureValueNode instanceof DOMElement) {
            throw new RuntimeException('SAML signature structure is incomplete.');
        }

        $canonical = $signedInfo->C14N(true, false);
        $signatureValue = base64_decode(preg_replace('/\s+/', '', $signatureValueNode->textContent) ?? '', true);

        if ($canonical === false || $signatureValue === false) {
            throw new RuntimeException('Unable to canonicalize SAML SignedInfo.');
        }

        $algorithm = (string) $xpath->evaluate('string(./ds:SignedInfo/ds:SignatureMethod/@Algorithm)', $signature);
        $opensslAlgo = str_contains($algorithm, 'sha256') ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;

        $verified = openssl_verify($canonical, $signatureValue, $publicKey, $opensslAlgo);
        if ($verified !== 1) {
            throw new RuntimeException('SAML signature verification failed.');
        }
    }

    private function normalizeCertificate(string $cert): string
    {
        $trimmed = trim($cert);
        if (str_contains($trimmed, 'BEGIN CERTIFICATE')) {
            return $trimmed;
        }

        $body = preg_replace('/\s+/', '', $trimmed) ?? '';

        return "-----BEGIN CERTIFICATE-----\n"
            . trim(chunk_split($body, 64, "\n"))
            . "\n-----END CERTIFICATE-----";
    }

    private function looksLikeEmail(string $value): bool
    {
        return $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function entityIdsMatch(string $left, string $right): bool
    {
        return rtrim(trim($left), '/') === rtrim(trim($right), '/');
    }

    private function urlsMatch(string $left, string $right): bool
    {
        return rtrim(trim($left), '/') === rtrim(trim($right), '/');
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function xpathLiteral(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'" . $value . "'";
        }

        if (!str_contains($value, '"')) {
            return '"' . $value . '"';
        }

        return "concat('" . str_replace("'", "', \"'\", '", $value) . "')";
    }
}
