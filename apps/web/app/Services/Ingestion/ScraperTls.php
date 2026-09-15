<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

/**
 * Certificate chains for government servers that send an incomplete one.
 *
 * psc.ap.gov.in serves its certificate without the GlobalSign intermediate that signed it.
 * A browser quietly fetches the missing link from the address printed inside the
 * certificate; curl does not, so every request to APPSC failed with "unable to get local
 * issuer certificate" while the site looked perfectly fine in Chrome.
 *
 * THE FIX IS TO SUPPLY THE MISSING LINK, NEVER TO SWITCH VERIFICATION OFF. The chain is
 * still verified to a root the machine already trusts; this only adds the certificate the
 * server forgot to send. `verify => false` would accept any certificate at all, on the one
 * integration whose output becomes the dates a student plans a year around.
 *
 * Intermediates live in resources/certs, each with a header recording where it came from
 * and why. The combined bundle is rebuilt only when one of its inputs changes.
 */
final class ScraperTls
{
    public function verifyOption(): string|bool
    {
        $extras = glob(resource_path('certs/*.pem')) ?: [];
        $base = $this->baseBundle();

        // Nothing to add, or no bundle to add it to: keep the platform's default verification.
        if ($extras === [] || $base === null) {
            return true;
        }

        $target = storage_path('app/certs/scraper-ca-bundle.pem');
        $newest = max(array_map(static fn (string $file): int => (int) filemtime($file), [...$extras, $base]));

        if (! is_file($target) || (int) filemtime($target) < $newest) {
            if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0755, true) && ! is_dir(dirname($target))) {
                return true;
            }

            $bundle = rtrim((string) file_get_contents($base))."\n";

            foreach ($extras as $file) {
                $bundle .= "\n".file_get_contents($file);
            }

            file_put_contents($target, $bundle, LOCK_EX);
        }

        return $target;
    }

    private function baseBundle(): ?string
    {
        $locations = openssl_get_cert_locations();

        foreach ([
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile'),
            $locations['default_cert_file'] ?? null,
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
