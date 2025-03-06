<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace qbank_mlangpreview\local\hooks\output;

/**
 * Hook callbacks for qbank_mlangpreview
 *
 * @package    qbank_mlangpreview
 * @copyright  2025 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_standard_head_html_generation {
    /**
     * Allows plugins to add any elements to the page &lt;head&gt; html tag.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function callback(\core\hook\output\before_standard_head_html_generation $hook): void {
        global $PAGE;
        if ($PAGE->pagetype == "question-bank-previewquestion-preview") {
            $content = self::add_language_list();
            $hook->add_html($content);
        }
    }

    public static function add_language_list() {
        global $DB, $USER;
        $langs = get_string_manager()->get_list_of_translations();
        $questionid = optional_param('id', 0, PARAM_INT);
        $lang = optional_param('lang', '', PARAM_TEXT);

        $question = $DB->get_record('question', ['id' => $questionid]);
        $tags = self::extract_language_tags($question->questiontext);
        if($lang == '' && empty($tags)) {
            return '';
        }
        // Add user's current language to the list of tags for easy switch back.
        $tags[] = $USER->lang;

        $langs = self::get_language_names($tags);
        $html = \html_writer::select($langs,'stack_lang_menu',$lang,'Select language',null,['id' => 'lang_menu']);
        $html = "
        <div id='lang_menu_container'>
            $html
        </div>
            <script>
                window.addEventListener('load', function () {
                    const langmenu = document.getElementById('menustack_lang_menu');
                    const anchor = document.getElementById('id_restart_question_preview');
                    langmenu.addEventListener('change', function(event) {
                        const language = event.target.value;
                        const url = new URL(window.location.href);
                        url.searchParams.delete('lang');
                        url.searchParams.append('lang', language);
                        window.location.href = url.href;
                        event.preventDefault();
                    });
                });
            </script>
        ";
        return $html;
    }

    private static function extract_language_tags(string $text): array {
        $patterns = [
            '/\[\[lang\s+code=[\'"]([a-z]{2}(?:_[a-z]{2})?)[\'"]]\]/i',  // STACK format
            '/\{mlang\s+([a-z]{2}(?:_[a-z]{2})?)\}/i',                   // MLang2 format
            '/<span\s+class="multilang"\s+lang="([a-z]{2}(?:_[a-z]{2})?)">/i',  // Core Moodle format variant 1
            '/<span\s+lang="([a-z]{2}(?:_[a-z]{2})?)"\\s+class="multilang">/i'  // Core Moodle format variant 2
        ];
        $languages = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $currentMatches)) {
                $languages = array_merge($languages, $currentMatches[1]);
            }
        }
        // Remove duplicates and return unique language codes
        return array_unique($languages);
    }

    private static function get_language_names(array $langcodes): array {
        $languages = get_string_manager()->get_list_of_translations();
        $languagenames = [];
        foreach ($langcodes as $code) {
            $languagenames[$code] = $languages[$code] ?? $code;
        }
        return $languagenames;
    }
}