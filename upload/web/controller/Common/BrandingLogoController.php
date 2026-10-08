<?php
declare(strict_types=1);

namespace Web\Controller\Common;

use Web\System\Core\Branding;
use Web\System\Core\Controller;

/**
 * Serves the owner-uploaded sidebar/login logo.
 *
 * The file lives outside the web root (storage_api/branding) and is reachable
 * only through this route, so neither the storage path nor the raw setting
 * value ever reaches the markup. It must work without a session — the login
 * screen renders before authentication exists — and the file name is 128 bits
 * of randomness generated at upload time, so it cannot be enumerated. MIME
 * comes from the extension allowlist of the upload validation, never from the
 * stored bytes.
 */
final class BrandingLogoController extends Controller
{
    public function show(): void
    {
        $branding = Branding::get($this->baseDir);
        $path = $branding->logoPath();

        if ($path === null) {
            $this->notFound();
            return;
        }

        $size = (int)@filesize($path);
        $fp = @fopen($path, 'rb');
        if ($fp === false || $size < 1) {
            if (is_resource($fp)) {
                fclose($fp);
            }
            $this->notFound();
            return;
        }

        header('Content-Type: ' . $branding->logoMimeType());
        header('Content-Length: ' . $size);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline');
        // Replaced whenever the owner uploads a new logo (mtime in the URL),
        // so an hour of private caching is safe on shared hosting.
        header('Cache-Control: private, max-age=3600');

        while (!feof($fp)) {
            $chunk = fread($fp, 65536);
            if ($chunk === false) {
                break;
            }
            echo $chunk;
        }
        fclose($fp);
        exit;
    }

    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo 'Not found';
        exit;
    }
}
