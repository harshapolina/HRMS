<?php
/**
 * Strip editor-only HR signature placeholders from letter HTML (print, mail, PDF).
 */
function stripSignaturePlaceholders($html) {
    if ($html === null || $html === '') {
        return $html;
    }

    $patterns = [
        '/<div[^>]*\bsig-placeholder\b[^>]*>[\s\S]*?<\/div>/i',
        '/<span[^>]*\bsig-placeholder\b[^>]*>[\s\S]*?<\/span>/i',
        '/<p[^>]*>\s*\[CLICK\s*HE\s*<\/p>/i',
        '/<p[^>]*>\s*RE\s*TO\s*INSERT\s*SIGNATURE\]\s*<\/p>/i',
        '/\[?\s*CLICK\s*HERE\s*TO\s*INSERT\s*SIGNATURE\s*\]?/i',
        '/CLICK\s*HERE\s*TO\s*INSERT\s*SIGNATURE/i',
        '/\[CLICK\s*HE\s*RE\s*TO\s*INSERT\s*SIGNATURE\]/i',
        '/\[CLICK\s*HE\s*/i',
        '/RE\s*TO\s*INSERT\s*SIGNATURE\]\s*/i',
        '/\{\{\s*\w*_?signature\s*\}\}/i',
        '/\[\s*HR[\'’]?s?\s+Signature\s*\]/i',
        '/\[\s*HR\s+Partner\s+Signature\s*\]/i',
        '/\[\s*Manager[\'’]?s?\s+Signature\s*\]/i',
        '/\[\s*Manager\/Supervisor\s+Signature\s*\]/i',
        '/\[\s*Supervisor[\'’]?s?\s+Signature\s*\]/i',
        '/\[\s*Candidate[\'’]?s?\s+Signature\s*\]/i',
        '/\[\s*Recipient[\'’]?s?\s+Signature\s*\]/i',
        '/\[\s*Employee\/Candidate\s+Signature\s*\]/i',
        '/\[\s*Employee[\'’]?s?\s+Signature\s*\]/i',
    ];

    foreach ($patterns as $pattern) {
        $html = preg_replace($pattern, '', $html);
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(
        '<?xml encoding="UTF-8"><div id="letter-strip-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " sig-placeholder ")]') as $node) {
        if ($node->parentNode) {
            $node->parentNode->removeChild($node);
        }
    }

    foreach ($xpath->query('//text()') as $textNode) {
        $original = $textNode->nodeValue;
        $cleaned = $original;
        $cleaned = preg_replace('/\[?\s*CLICK\s*HERE\s*TO\s*INSERT\s*SIGNATURE\s*\]?/i', '', $cleaned);
        $cleaned = preg_replace('/\[CLICK\s*HE\s*RE\s*TO\s*INSERT\s*SIGNATURE\]/i', '', $cleaned);
        $cleaned = preg_replace('/CLICK\s*HERE\s*TO\s*INSERT\s*SIGNATURE/i', '', $cleaned);
        $cleaned = preg_replace('/\[CLICK\s*HE\s*/i', '', $cleaned);
        $cleaned = preg_replace('/RE\s*TO\s*INSERT\s*SIGNATURE\]\s*/i', '', $cleaned);
        
        $cleaned = preg_replace('/\{\{\s*\w*_?signature\s*\}\}/i', '', $cleaned);
        $cleaned = preg_replace('/\[\s*HR[\'’]?s?\s+Signature\s*\]/i', '', $cleaned);
        $cleaned = preg_replace('/\[\s*HR\s+Partner\s+Signature\s*\]/i', '', $cleaned);
        $cleaned = preg_replace('/\[\s*Manager[\'’]?s?\s+Signature\s*\]/i', '', $cleaned);
        $cleaned = preg_replace('/\[\s*Manager\/Supervisor\s+Signature\s*\]/i', '', $cleaned);
        $cleaned = preg_replace('/\[\s*Supervisor[\'’]?s?\s+Signature\s*\]/i', '', $cleaned);
        $cleaned = preg_replace('/\[\s*Candidate[\'’]?s?\s+Signature\s*\]/i', '', $cleaned);
        $cleaned = preg_replace('/\[\s*Recipient[\'’]?s?\s+Signature\s*\]/i', '', $cleaned);
        $cleaned = preg_replace('/\[\s*Employee\/Candidate\s+Signature\s*\]/i', '', $cleaned);
        $cleaned = preg_replace('/\[\s*Employee[\'’]?s?\s+Signature\s*\]/i', '', $cleaned);

        if ($cleaned !== $original) {
            if (trim($cleaned) === '') {
                if ($textNode->parentNode) {
                    $textNode->parentNode->removeChild($textNode);
                }
            } else {
                $textNode->nodeValue = $cleaned;
            }
        }
    }

    $root = $dom->getElementById('letter-strip-root');
    if ($root) {
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        return $out;
    }

    return $html;
}

/**
 * Clean offer letter HTML for browser printing by stripping repeating
 * headers, footers, watermarks, and internal styling so it inherits
 * the print_offer.php page default styles and repeating elements.
 */
function cleanOfferLetterHtmlForPrint($html) {
    if ($html === null || $html === '') {
        return $html;
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    // Use UTF-8 encoding declaration to handle special characters properly
    $dom->loadHTML(
        '<?xml encoding="UTF-8"><div id="letter-clean-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    
    // Classes to strip: header-fixed, footer-fixed, letter-watermark
    $classesToStrip = ['header-fixed', 'footer-fixed', 'letter-watermark'];
    foreach ($classesToStrip as $class) {
        $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]');
        foreach ($nodes as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    // Also strip out any <style> tags
    $styleNodes = $dom->getElementsByTagName('style');
    while ($styleNodes->length > 0) {
        $node = $styleNodes->item(0);
        if ($node->parentNode) {
            $node->parentNode->removeChild($node);
        }
    }

    $root = $dom->getElementById('letter-clean-root');
    if ($root) {
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        return $out;
    }

    return $html;
}

