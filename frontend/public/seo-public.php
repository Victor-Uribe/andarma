<?php
/**
 * ANDARMA - Metadatos SEO para las rutas PUBLICAS de Vue.
 * Usar solamente tras revisar el enrutamiento Apache/Nginx de Hestia.
 * No hace cambios a Laravel, base de datos, admin o autenticacion.
 */
declare(strict_types=1);

const ANDARMA_URL = 'https://andarma.com.mx';
const SEO_BLOCK_START = '<meta name="andarma-seo-start" content="v2">';
const SEO_BLOCK_END = '<meta name="andarma-seo-end" content="v2">';

function seoEscape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function seoShort(string $value, int $max = 155): string {
    $value = trim((string)preg_replace('/\s+/u', ' ', strip_tags($value)));
    if (function_exists('mb_substr')) return mb_substr($value, 0, $max, 'UTF-8');
    return substr($value, 0, $max);
}
function seoPublicDevelopments(): ?array {
    // Solo llama al endpoint PUBLICO de Laravel que filtra status=active.
    $url = ANDARMA_URL . '/api/public/developments';
    $cache = sys_get_temp_dir() . '/andarma-public-seo-' . hash('sha256', $url) . '.json';
    $stored = (is_file($cache) && is_readable($cache)) ? @file_get_contents($cache) : false;
    if ($stored !== false && filemtime($cache) > time() - 300) {
        $parsed = json_decode($stored, true);
        if (is_array($parsed) && isset($parsed['data']) && is_array($parsed['data'])) return $parsed['data'];
    }
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 7,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'ANDARMA-PublicSEO/2.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $status !== 200 || strlen($raw) > 3000000) {
        // Ante error transitorio, no inventa desarrollos. Se tolera cache reciente.
        if ($stored !== false && filemtime($cache) > time() - 3600) {
            $parsed = json_decode($stored, true);
            if (is_array($parsed) && isset($parsed['data']) && is_array($parsed['data'])) return $parsed['data'];
        }
        return null;
    }
    $parsed = json_decode($raw, true);
    if (!is_array($parsed) || !isset($parsed['data']) || !is_array($parsed['data'])) return null;
    @file_put_contents($cache, $raw, LOCK_EX);
    return $parsed['data'];
}
function seoPublicImage(array $item): string {
    foreach (['image_url', 'cover_image_url', 'hero_image_url', 'banner_url'] as $key) {
        $candidate = trim((string)($item[$key] ?? ''));
        if ($candidate === '' || str_starts_with($candidate, 'data:')) continue;
        if (str_starts_with($candidate, '/') && !str_starts_with($candidate, '//')) {
            return ANDARMA_URL . $candidate;
        }
        if (filter_var($candidate, FILTER_VALIDATE_URL)) {
            $parts = parse_url($candidate);
            $host = strtolower((string)($parts['host'] ?? ''));
            if (($parts['scheme'] ?? '') === 'https' && in_array($host, ['andarma.com.mx', 'www.andarma.com.mx'], true)) {
                return $candidate;
            }
        }
    }
    return ANDARMA_URL . '/og-andarma.jpg';
}
function seoMetaTag(string $type, string $name, string $value): string {
    return '<meta ' . $type . '="' . seoEscape($name) . '" content="' . seoEscape($value) . '">';
}
function seoBlock(array $meta): string {
    $json = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $meta['url'] . '#webpage',
        'url' => $meta['url'],
        'name' => $meta['title'],
        'description' => $meta['description'],
        'inLanguage' => 'es-MX',
        'isPartOf' => ['@type' => 'WebSite', 'name' => 'ANDARMA', 'url' => ANDARMA_URL . '/'],
        'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $meta['image']],
    ];
    $data = [
        SEO_BLOCK_START,
        '<link rel="canonical" href="' . seoEscape($meta['url']) . '">',
        '<link rel="icon" type="image/x-icon" href="/favicon.ico">',
        '<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">',
        '<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">',
        '<link rel="manifest" href="/site.webmanifest">',
        seoMetaTag('property', 'og:locale', 'es_MX'),
        seoMetaTag('property', 'og:type', 'website'),
        seoMetaTag('property', 'og:site_name', 'ANDARMA'),
        seoMetaTag('property', 'og:url', $meta['url']),
        seoMetaTag('property', 'og:title', $meta['title']),
        seoMetaTag('property', 'og:description', $meta['description']),
        seoMetaTag('property', 'og:image', $meta['image']),
        seoMetaTag('property', 'og:image:alt', $meta['title']),
        seoMetaTag('name', 'twitter:card', 'summary_large_image'),
        seoMetaTag('name', 'twitter:title', $meta['title']),
        seoMetaTag('name', 'twitter:description', $meta['description']),
        seoMetaTag('name', 'twitter:image', $meta['image']),
        '<script type="application/ld+json">' . json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>',
        SEO_BLOCK_END,
    ];
    return implode("\n", $data);
}
function seoServe(string $path): void {
    $home = ANDARMA_URL . '/';
    $meta = [
        'url' => $home,
        'title' => 'ANDARMA | Desarrollos inmobiliarios en el Estado de México',
        'description' => 'Conoce los proyectos inmobiliarios de ANDARMA. Explora desarrollos, ubicaciones y planos de terrenos en el Estado de México.',
        'image' => ANDARMA_URL . '/og-andarma.jpg',
    ];
    $notFound = false;
    if ($path === '/desarrollos') {
        $meta = [
            'url' => ANDARMA_URL . '/desarrollos',
            'title' => 'Desarrollos y terrenos en el Estado de México | ANDARMA',
            'description' => 'Explora los desarrollos publicados de ANDARMA. Consulta ubicaciones, características y planos para conocer cada proyecto inmobiliario.',
            'image' => ANDARMA_URL . '/og-desarrollos.jpg',
        ];
        if (!is_file(__DIR__ . '/og-desarrollos.jpg')) $meta['image'] = ANDARMA_URL . '/og-andarma.jpg';
    } elseif (preg_match('~^/desarrollos/([a-z0-9-]{1,120})$~', $path, $match)) {
        $records = seoPublicDevelopments();
        if ($records === null) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store');
            echo 'El contenido del desarrollo no esta disponible temporalmente.';
            return;
        }
        $item = null;
        foreach ($records as $row) {
            if (!is_array($row)) continue;
            if ((string)($row['slug'] ?? $row['id'] ?? '') === $match[1]) { $item = $row; break; }
        }
        if ($item === null) {
            $notFound = true;
        } else {
            $name = seoShort((string)($item['name'] ?? ''), 75);
            $location = seoShort((string)($item['location'] ?? ''), 80);
            $text = seoShort((string)($item['description'] ?? ''), 155);
            $where = $location !== '' ? ' en ' . $location : '';
            $meta = [
                'url' => ANDARMA_URL . '/desarrollos/' . rawurlencode($match[1]),
                'title' => $name . ' | Desarrollo inmobiliario ANDARMA',
                'description' => ($text !== '' ? $text : "Conoce {$name}{$where}. Consulta la ubicación y explora la información y el plano del desarrollo en ANDARMA."),
                'image' => seoPublicImage($item),
            ];
        }
    } else {
        $notFound = true;
    }
    if ($notFound) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo 'Desarrollo no encontrado.';
        return;
    }
    $base = __DIR__ . '/index.html';
    if (!is_file($base)) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Falta publicar dist/index.html de Vue.';
        return;
    }
    $html = file_get_contents($base);
    if ($html === false || !str_contains($html, SEO_BLOCK_START) || !str_contains($html, SEO_BLOCK_END)) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Version HTML SEO no reconocida. Revisar despliegue.';
        return;
    }
    $html = preg_replace('~<title>.*?</title>~is', '<title>' . seoEscape($meta['title']) . '</title>', $html, 1);
    $html = preg_replace('~<meta\s+name=["\']description["\']\s+content=["\'][^"\']*["\']\s*/?>~i', seoMetaTag('name', 'description', $meta['description']), $html, 1);
    $pattern = '~' . preg_quote(SEO_BLOCK_START, '~') . '.*?' . preg_quote(SEO_BLOCK_END, '~') . '~s';
    $html = preg_replace_callback($pattern, static fn() => seoBlock($meta), $html, 1);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: public, max-age=120');
    echo $html;
}
function seoSitemap(): void {
    $rows = seoPublicDevelopments();
    if ($rows === null) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo 'Sitemap temporalmente no disponible.';
        return;
    }
    $urls = [ANDARMA_URL . '/', ANDARMA_URL . '/desarrollos'];
    foreach ($rows as $item) {
        if (!is_array($item)) continue;
        $slug = (string)($item['slug'] ?? $item['id'] ?? '');
        if (preg_match('/^[a-z0-9-]{1,120}$/D', $slug)) $urls[] = ANDARMA_URL . '/desarrollos/' . $slug;
    }
    $urls = array_unique($urls);
    $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
    foreach ($urls as $url) {
        $xml[] = '  <url><loc>' . htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc></url>';
    }
    $xml[] = '</urlset>';
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo implode("\n", $xml) . "\n";
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
if ($path === '/sitemap.xml') seoSitemap();
else seoServe($path);
