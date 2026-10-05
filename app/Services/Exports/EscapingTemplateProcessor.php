<?php

namespace App\Services\Exports;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * TemplateProcessor that XML-escapes every value passed to setValue().
 *
 * PhpWord only escapes when Settings::setOutputEscapingEnabled(true) is on,
 * which it isn't by default - so a founder typing "1st Runner Up & People's
 * Choice" put a bare "&" into word/document.xml and Word refused to open the
 * export ("Word experienced an error trying to open the file"). Escaping here
 * covers every placeholder in every document without each call site having
 * to remember it.
 *
 * Use setXmlValue() only for values that are deliberately WordprocessingML
 * (e.g. </w:t><w:br/><w:t> line breaks) and whose text parts are already
 * escaped.
 */
class EscapingTemplateProcessor extends TemplateProcessor
{
    public function setValue($search, $replace, $limit = self::MAXIMUM_REPLACEMENTS_DEFAULT): void
    {
        $escape = fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $replace = is_array($replace) ? array_map($escape, $replace) : $escape($replace);

        parent::setValue($search, $replace, $limit);
    }

    /**
     * Insert raw WordprocessingML - the caller is responsible for escaping.
     */
    public function setXmlValue($search, $replace, $limit = self::MAXIMUM_REPLACEMENTS_DEFAULT): void
    {
        parent::setValue($search, $replace, $limit);
    }
}
