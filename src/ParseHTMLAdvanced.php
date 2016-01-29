<?php

namespace IjorTengab;

/**
 * Versi lebih complex dari ParseHTML;
 */
class ParseHTMLAdvanced extends ParseHTML
{

    public static $class_name = __CLASS__;

    /**
     * Mengubah ELEMENT menjadi susunan array yang berisi informasi element
     * terdiri dari tag (ditandai dengan t), attributes (ditandai dengan a),
     * dan children (descdent) dari element tersebut (ditandai dengan c).
     *
     * @param $element string
     *   Merupakan ELEMENT, lihat pada definisi diatas.
     *
     * Contoh:
     *
     * ```php
     *
     *     $contents = <<<HTML
     *     <p>Iwan Fals, <span>Sore Tugu Pancoran</span></p>
     *     HTML;
     *
     *     $extract = ParseHtml::extract($contents);
     *     // Hasil dari var_export($extract);
     *
     *     $extract = [
     *         't' => 'p',
     *         'a' => [],
     *         'c' => [
     *             0 => 'Iwan Fals, ',
     *             1 => [
     *                 't' => 'span',
     *                 'a' => [],
     *                 'c' => [
     *                     0 => 'Sore Tugu Pancoran',
     *                 ],
     *             ],
     *         ],
     *     ];
     *
     * ```
     */
    public static function extract($element)
    {
        $info = [];
        list($starttag, $contents, $endtag) = self::parseElement($element);
        // Starttag dan endtag harus ada.
        if ($starttag === false || $endtag === false) {
            return $element;
        }
        $attr = self::extractAttributes($starttag);
        $tag = self::getTagName($starttag);
        $info['t'] = $tag;
        $info['a'] = $attr;
        $children = [];

        // Code dibawah ini mirip dengan getElementChildren.
        $offset = strlen($starttag);
        $storage = array();
        $find_lt = $lt = '<';
        $find_rt = $rt = '>';
        $scoupe = $contents;
        $distance_lt = strpos($scoupe, $find_lt);
        $last_rt = strlen($starttag);
        while ($distance_lt !== false) {
            // Karakter setelah < harus alphabet.
            $char = substr($scoupe, $distance_lt + strlen($lt), 1);
            if (false == preg_match('/[a-zA-Z]/', $char)) {
                // Skip $lt ini, Cari lagi yang lebih valid.
                $offset += $distance_lt + strlen($lt);
                $scoupe = substr($element, $offset);
                $distance_lt = stripos($scoupe, $find_lt);
                continue;
            }
            // Ketemu tag <alphabet, periksa text sebelumnya.
            $child_starttag_lt_position = $distance_lt + $offset;
            $text = substr($element, $last_rt, $child_starttag_lt_position - $last_rt);
            $text = self::trimHtml($text);
            empty($text) or $children[] = $text;
            // Ketemu tag <alphabet, maka ketemu element child.
            $distance_rt = strpos($scoupe, $find_rt, $distance_lt);
            $child_starttag_rt_position = $distance_rt + $offset;
            $child_starttag = substr($element, $child_starttag_lt_position, $child_starttag_rt_position + strlen($rt) - $child_starttag_lt_position);
            $child = self::constructElement($child_starttag_lt_position, $child_starttag, $element);

            // Update offset dan scoupe, dan last_rt.
            $offset += $distance_lt + strlen($child);
            $last_rt = $offset;
            $scoupe = substr($element, $offset);
            // Save dan...
            // Lakukan recursive.
            $children[] = self::extract($child);
            // Cari lagi.
            $distance_lt = stripos($scoupe, $find_lt);
        }
        $text = substr($element, $last_rt, -1 * strlen($endtag));
        $text = self::trimHtml($text);
        empty($text) or $children[] = $text;
        $info['c'] = $children;
        return $info;
    }

    /**
     * Melakukan extract element seperti methode ::extract() tapi dengan fokus
     * mencari value saja, seperti yang tampil di browser.
     */
    public static function extractValueOnly($element)
    {
        $extract = self::extract($element);
        $storage = [];
        self::_extractValueOnly($extract, $storage);
        $sanitize = array_shift($storage);
        return $sanitize;
    }

    /**
     * Memiliki fungsi yang sama dengan method ::extract(), namun khusus
     * ditujukan untuk instance.
     *
     * @return
     *   Untuk array hasil extract jika hanya satu element maka akan di
     *   array_unshift(), sehingga ada dua kemungkinan hasil, yakni:
     *     1. $array_extracted, atau ...
     *     2. array(
     *            0 => $array_extracted,
     *            1 => $array_extracted,
     *        );
     *
     * Contoh:
     *
     * ```php
     *
     *     use IjorTengab\ParseHtml;
     *
     *     $contents = <<<HTML
     *     <div class="notice">
     *         <span>Messages</span>
     *         <span><a>Todo</a></span>
     *         <span>Warning</span>
     *     </div>
     *     HTML;
     *
     *     // Penggunaan.
     *     $html = new ParseHtml($contents);
     *     $extract_1 = $html->extractElement();
     *     $extract_2 = $html->extractElement(true);
     *     $extract_3 = $html->find('span')->extractElement();
     *     $extract_4 = $html->find('span')->extractElement(true);
     *
     *     // Hasil dari var_export($extract_1);
     *     $extract_1 = [
     *         't' => 'div',
     *         'a' => [
     *             'class' => 'notice',
     *         ],
     *         'c' => [
     *             0 => [
     *                 't' => 'span',
     *                 'a' => [],
     *                 'c' => [
     *                     0 => 'Messages',
     *                 ],
     *             ],
     *             1 => [
     *                 't' => 'span',
     *                 'a' => [],
     *                 'c' => [
     *                     0 => [
     *                         't' => 'a',
     *                         'a' => [],
     *                         'c' => [
     *                             0 => 'Todo',
     *                         ],
     *                     ],
     *                 ],
     *             ],
     *             2 => [
     *                 't' => 'span',
     *                 'a' => [],
     *                 'c' => [
     *                     0 => 'Warning',
     *                 ],
     *             ],
     *         ],
     *     ];
     *
     *     // Hasil dari var_export($extract_2);
     *     $extract_2 = [
     *         0 => 'Messages',
     *         1 => 'Todo',
     *         2 => 'Warning',
     *     ];
     *
     *     // Hasil dari var_export($extract_3);
     *     $extract_3 = [
     *         0 => [
     *             't' => 'span',
     *             'a' => [],
     *             'c' => [
     *                 0 => 'Messages',
     *             ],
     *         ],
     *         1 => [
     *             't' => 'span',
     *             'a' => [],
     *             'c' => [
     *                 0 => [
     *                     't' => 'a',
     *                     'a' => [],
     *                     'c' => [
     *                         0 => 'Todo',
     *                     ],
     *                 ],
     *             ],
     *         ],
     *         2 => [
     *             't' => 'span',
     *             'a' => [],
     *             'c' => [
     *                 0 => 'Warning',
     *             ],
     *         ],
     *     ];
     *
     *     // Hasil dari var_export($extract_4) sama dengan hasil dari
     *     // var_export($extract_2).
     * ```
     */
    public function extractElement($value_only = false)
    {
        $elements = $this->getElements();
        $storage = [];
        foreach ($elements as $position => $element) {
            if ($value_only) {
                $storage[] = $this->extractValueOnly($element);
            }
            else {
                $storage[] = $this->extract($element);
            }
        }
        // Sanitize, if only one founded.
        if (count($storage) === 1) {
            $sanitize = array_shift($storage);
            return $sanitize;
        }
        return $storage;
    }

    /**
     * Memiliki fungsi yang sama dengan method ::extractElement() namun khusus
     * ditujukan pada element table. Table nested (table didalam table)
     * akan di-promote sebagai table mandiri. Method  ini memiliki kelemahan
     * pada table yang identik (sama persis dalam hal panjang string dan
     * karakter), dimana table-table yang identik tersebut akan dianggap
     * sebagai satu table.
     *
     * @return
     *   Untuk array hasil extract jika hanya satu element maka akan di
     *   array_unshift(), sehingga ada dua kemungkinan hasil, yakni:
     *     1. $array_extracted, atau ...
     *     2. array(
     *            0 => $array_extracted,
     *            1 => $array_extracted,
     *        );
     *
     * Contoh:
     *
     * ```php
     *
     *     use IjorTengab\ParseHtml;
     *
     *     $contents = <<<HTML
     *     Sometext without tag element.
     *     <table>
     *         <tr><td>Todo</td></tr>
     *     </table>
     *     <p>Text wrap by paragraph element.</p>
     *     HTML;
     *
     *     // Penggunaan.
     *     $html = new ParseHtml($contents);
     *     $extract_1 = $html->extractTable();
     *     $extract_2 = $html->extractTable(true);
     *
     *     // Hasil dari var_export($extract_1);
     *
     *     $extract_1 = [
     *         't' => 'table',
     *         'a' => [],
     *         'c' => [
     *             0 => [
     *                 't' => 'tr',
     *                 'a' => [],
     *                 'c' => [
     *                     0 => [
     *                         't' => 'td',
     *                         'a' => [],
     *                         'c' => [
     *                             0 => 'Todo',
     *                         ],
     *                     ],
     *                 ],
     *             ],
     *         ],
     *     ];
     *
     *     // Hasil dari var_export($extract_2);
     *     $extract_2 = 'Todo';
     *
     * ```
     *
     * Contoh table nested dan identik:
     *
     * ```php
     *
     *     use IjorTengab\ParseHtml;
     *
     *     $contents = <<<HTML
     *     <table class="common">
     *         <tr><td>Messages</td></tr>
     *         <tr><td>Notices</td></tr>
     *         <tr><td>Todo</td></tr>
     *         <tr>
     *             <td>
     *                 <table class="common"><tr><td><!-- Table identik, hanya satu yang dapat di extract --></td></tr></table>
     *                 <table class="common"><tr><td><!-- Table identik, hanya satu yang dapat di extract --></td></tr></table>
     *                 <table class="common"><tr><td>&nbsp;</td></tr></table>
     *             </td>
     *         </tr>
     *     </table>
     *     HTML;
     *
     *     // Penggunaan.
     *     $html = new ParseHtml($contents);
     *     $extract_1 = $html->find('table.common')->extractTable();
     *     $extract_2 = $html->find('table.common')->extractTable(true);
     *
     *     // Hasil dari var_export($extract_1);
     *     $extract_1 = [
     *         0 => [
     *             't' => 'table',
     *             'a' => [
     *                 'class' => 'common',
     *             ],
     *             'c' => [
     *                 0 => [
     *                     't' => 'tr',
     *                     'a' => [],
     *                     'c' => [
     *                         0 => [
     *                             't' => 'td',
     *                             'a' => [],
     *                             'c' => [
     *                                 0 => 'Messages',
     *                             ],
     *                         ],
     *                     ],
     *                 ],
     *                 1 => [
     *                     't' => 'tr',
     *                     'a' => [],
     *                     'c' => [
     *                         0 => [
     *                             't' => 'td',
     *                             'a' => [],
     *                             'c' => [
     *                                 0 => 'Notices',
     *                             ],
     *                         ],
     *                     ],
     *                 ],
     *                 2 => [
     *                     't' => 'tr',
     *                     'a' => [],
     *                     'c' => [
     *                         0 => [
     *                             't' => 'td',
     *                             'a' => [],
     *                             'c' => [
     *                                 0 => 'Todo',
     *                             ],
     *                         ],
     *                     ],
     *                 ],
     *                 3 => [
     *                     't' => 'tr',
     *                     'a' => [],
     *                     'c' => [
     *                         0 => [
     *                             't' => 'td',
     *                             'a' => [],
     *                             'c' => [],
     *                         ],
     *                     ],
     *                 ],
     *             ],
     *         ],
     *         1 => [
     *             't' => 'table',
     *             'a' => [
     *                 'class' => 'common',
     *             ],
     *             'c' => [
     *                 0 => [
     *                     't' => 'tr',
     *                     'a' => [],
     *                     'c' => [
     *                         0 => [
     *                             't' => 'td',
     *                             'a' => [],
     *                             'c' => [
     *                                 0 => '<!-- Table identik, hanya satu yang dapat di extract -->',
     *                             ],
     *                         ],
     *                     ],
     *                 ],
     *             ],
     *         ],
     *         2 => [
     *             't' => 'table',
     *             'a' => [
     *                 'class' => 'common',
     *             ],
     *             'c' => [
     *                 0 => [
     *                     't' => 'tr',
     *                     'a' => [],
     *                     'c' => [
     *                         0 => [
     *                             't' => 'td',
     *                             'a' => [],
     *                             'c' => [
     *                                 0 => '&nbsp;',
     *                             ],
     *                         ],
     *                     ],
     *                 ],
     *             ],
     *         ],
     *     ];
     *
     *     // Hasil dari var_export($extract_2);
     *     $extract_2 = [
     *         0 => [
     *             0 => 'Messages',
     *             1 => 'Notices',
     *             2 => 'Todo',
     *             3 => '',
     *         ],
     *         1 => '<!-- Table identik, hanya satu yang dapat di extract -->',
     *         2 => '&nbsp;',
     *     ];
     *
     * ```
     */
    public function extractTable($value_only = false)
    {
        // Mengulangi pencarian element table untuk mendukung dua tipe pencarian
        // sebagai berikut:
        // $tables = $html->extractTable();
        // dan sekaligus juga mempromote nested table agar menjadi baris sendiri
        // didalam array yang mana dilakukan dengan pencarian sebagai berikut:
        // $tables = $html->find('table.common')->extractTable();
        $elements = $this->getElements();
        $length = $this->length;
        $tables = [];
        foreach($elements as $p => $element) {
            $_tables = $this->getElementByTag('table', $element);
            $this->constructElements($_tables, $element);
            $this->addPosition($_tables, $p);
            $tables += $_tables;
        }
        // Tidak ada table?, kembalikan array kosong.
        if (empty($tables)) {
            return [];
        }
        // Semua nested table telah dipromote.
        // Table nested perlu kita hapus, agar tidak mengacaukan
        // hasil, karena table tersebut sudah di-"promote" didalam variable
        // $tables.
        $storage = [];
        foreach ($tables as $table_html) {
            $html = new static::$class_name($table_html);
            if ($nested_table = $html->find('table table')->getElements()) {
                $table_html = str_replace($nested_table, '', $table_html);
            }
            // Table ada bermacam format
            // table > tr > th
            // table > tr > td
            // table > thead > tr > th
            // table > thead > tr > td
            // table > tbody > tr > td
            // table > tbody > tr > td
            if ($value_only) {
                $extract = $this->extractValueOnly($table_html);
            }
            else {
                $extract = $this->extract($table_html);
            }
            $storage[] = $extract;
        }

        // Sanitize, if only one founded.
        if (count($storage) === 1) {
            $sanitize = array_shift($storage);
            return $sanitize;
        }
        return $storage;
    }

    /**
     * Mengambil informasi element form, berupa attribute name dan value
     * atau opsi value.
     *
     * @link
     *   http://www.w3schools.com/html/html_form_elements.asp
     *
     * @todo
     *   support for HTML5 element: <datalist> <keygen> <output>
     *
     * @param $selector string
     *   Custom css selector, if null, the selector is
     *   input, textarea, select, button
     *
     * @return
     *   Associative array that represented of element (name => value)
     *
     * Contoh:
     *
     * ```php
     *
     *     use IjorTengab\ParseHtml;
     *
     *     $contents = <<<HTML
     *     <div class="form-common">
     *         <form action="url">
     *            <input type="text" name="firstname" value="IjorTengab">
     *            <input type="text" name="lastname" value="">
     *            <input type="hidden" name="token" value="345d8d6c92c99965edf282f82e00cf39">
     *            <input type="radio" name="bio[sex]" value="male">
     *            <input type="radio" name="bio[sex]" value="female">
     *            <input type="checkbox" name="hobby[]" value="Read a Book">
     *            <input type="checkbox" name="hobby[]" value="Hiking">
     *            <input type="password" name="drupal7_field[und][0][value]" value="">
     *            <span>Warning</span>
     *        </form>
     *     </div>
     *     HTML;
     *
     *     // Penggunaan.
     *     $html = new ParseHtml($contents);
     *     $fields_1 = $html->extractForm();
     *     $fields_2 = $html->find('form#id')->extractForm();
     *
     *     // Hasil dari var_export($fields_1);
     *     $fields_1 = [
     *         'firstname' => 'IjorTengab',
     *         'lastname' => '',
     *         'token' => '345d8d6c92c99965edf282f82e00cf39',
     *         'bio' => [
     *             'sex' => [
     *                 0 => 'male',
     *                 1 => 'female',
     *             ],
     *         ],
     *         'hobby' => [
     *             0 => 'Read a Book',
     *             1 => 'Hiking',
     *         ],
     *         'drupal7_field' => [
     *             'und' => [
     *                 0 => [
     *                     'value' => '',
     *                 ],
     *             ],
     *         ],
     *     ];
     *
     *     // Hasil dari var_export($fields_2);
     *     $fields_2 = [];
     *
     * ```
     *
     *
     */
    public function extractForm($selector = null)
    {
        if (is_null($selector)) {
            $selector = 'input, textarea, select, button';
        }
        $storage = array();
        $elements = $this->find($selector)->getElements();
        ksort($elements);
        foreach($elements as $element) {
            list($starttag, $contents, $endtag) = $this->parseElement($element);
            $attr = $this->extractAttributes($starttag);
            $tag = $this->getTagName($starttag);
            if (isset($attr['name'])) {
                $name = $attr['name'];
                switch ($tag) {
                    case 'select':
                        $select = new static::$class_name($element);
                        $options = $select->find('option')->getElements();
                        $value = array();
                        foreach ($options as $option) {
                            $option_attr = $this->extractAttributes($option);
                            !array_key_exists('value', $option_attr) or $value[] = $option_attr['value'];
                        }
                        (count($value) != 0) or $value = null;
                        break;

                    case 'textarea':
                        $value = $contents;
                        break;

                    default:
                        $value = isset($attr['value']) ? $attr['value'] : null;
                        break;
                }
                // Bagaimana jika $name seperti ini:
                // 1. field_image[und][0][display] (kasus Drupal)
                // 2. field[]
                // 3. field_image[und][0][display][] (Gabungan keduanya)
                // Oleh karena itu, dibuat fungsi baru
                // ::_extractFormCreateNestedArray();
                $_storage = [];
                $this->_extractFormCreateNestedArray($_storage, $name, $value);
                // Pada kasus input type radio, dimana attribute name sama,
                // maka kita perlu menjaga seluruh value tetap exists.
                // untuk itu kita gunakan array_merge_recursive alih-alih
                // array_replace_recursive
                $storage = array_merge_recursive($storage, $_storage);
            }
        }
        return $storage;
    }

    /**
     * Mempersiapkan element-element form yang akan dipost,
     * dimana input type submit hanya diijinkan satu saja.
     *
     * Todo, support for HTML5 element: <datalist> <keygen> <output>
     *
     * @param $trigger string
     *   Value dari attribute name pada input submit yang akan dijadikan
     *   trigger untuk mengirim form.
     */
    public function preparePostForm($trigger)
    {
        $fields = $this->extractForm();
        $submit = $this->extractForm('[type=submit]');

        // Buang semua input submit kecuali 'trigger'.
        unset($submit[$trigger]);
        return array_diff_assoc($fields, $submit);
    }

    protected static function _extractValueOnly($array, &$storage)
    {
        if (is_string($array)) {
            return $storage[] = $array;
        }
        if (empty($array['c'])) {
            return $storage[] = '';
        }
        $count = count($array['c']);
        if ($count === 1) {
            $children = array_shift($array['c']);
            self::_extractValueOnly($children, $storage);
        }
        else {
            $_storage = [];
            while($children = array_shift($array['c'])) {
                self::_extractValueOnly($children, $_storage);
            }
            $storage[] = $_storage;
        }
    }

    /**
     * Membuat nested array, code bersumber dari drupal 7 pada fungsi
     * drupal_parse_info_format().
     */
    protected function _extractFormCreateNestedArray(&$storage, $name, $value)
    {
        // Parse array syntax.
        $keys = preg_split('/\]?\[/', rtrim($name, ']'));
        $last = array_pop($keys);
        $parent = &$storage;

        // Create nested arrays.
        foreach ($keys as $key) {
            if ($key == '') {
                $key = count($parent);
            }
            if (!isset($parent[$key]) || !is_array($parent[$key])) {
                $parent[$key] = array();
            }
            $parent = &$parent[$key];
        }

        // Insert actual value.
        if ($last == '') {
            $last = count($parent);
        }
        $parent[$last] = $value;
    }
}
