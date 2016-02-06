<?php

namespace IjorTengab;

/**
 * ParseHTML is PHP library working like jQuery, give you an easy way to get
 * any information from text html.
 *
 * Definisi:
 *
 *   1. ELEMENT
 *
 *      Komponen lengkap element yakni terdiri dari starttag, contents,
 *      dan endtag (kecuali void element). Sudah trim dan tidak boleh
 *      ada karakter lain sebelum startag dan setelah endtag. Untuk
 *      referensi void element, dapat melihat method
 *      ::validateTagVoidElement().
 *
 *      Contoh:
 *
 *      ```php
 *      // ELEMENT yang benar:
 *      $element = '<body class="a">bla bla bla</body>';
 *      // ELEMENT yang salah:
 *      $element = 'sometext<body class="a">bla bla bla</body>';
 *      ```
 *
 *   2. ELEMENTS
 *
 *      Array sederhana satu dimensi, dimana pada value merupakan ELEMENT, dan
 *      key adalah posisi dari awal dokumen html (pada $this->raw) menuju
 *      ELEMENT. Posisi ini idem dengan nilai yang didapat dari fungsi strpos.
 *
 *      Contoh:
 *
 *      ```php
 *      $elements = [
 *          '30' => '<meta keyword="abc">',
 *          '50' => '<body class="a">bla bla bla</body>',
 *          '250' => '<img class="b">',
 *          '838' => '<div class="c"><span></span></div>',
 *          '2530' => '<div class></div>',
 *      ];
 *      ```
 *
 *   3. STARTAG
 *
 *      Bagian dari ELEMENT, yakni hanya pada startag saja. Sesudah tag pembuka
 *      dan penutup tidak boleh ada karakter lain.
 *
 *      Contoh:
 *
 *      ```php
 *      // STARTAG yang benar:
 *      $startag = '<body class="a">';
 *      // STARTAG yang salah:
 *      $startag = '<body class="a">bla bla bla</body>';
 *      ```
 *
 *   4. STARTAGS
 *
 *      Idem dengan ELEMENTS, namun pada value adalah starttag.
 *
 *      Contoh:
 *
 *      ```php
 *      $startags = [
 *          '5' => '<body class="a">',
 *          '25' => '<img class="b">',
 *          '83' => '<div class="c">',
 *          '253' => '<div class>',
 *      ];
 *      ```
 */
class ParseHTML
{

    public static $class_name = __CLASS__;

    /**
     * Data mentah keseluruhan dokumen html.
     *
     * Dapat bertipe string atau null. Properti ini menjadi rujukan utama untuk
     * pencarian dan lain-lain. Jika tercipta object baru hasil eksekusi
     * method find(), maka property $raw dari object baru tersebut akan
     * sama dengan property $raw dari object ini.
     */
    private $raw;

    /**
     * Merupakan ELEMENTS, lihat pada Definisi FAQ.
     *
     * Untuk mengambil info properti $elements ini, gunakan method
     * getElements(). Jika $elements merupakan empty array, maka itu berarti
     * $raw digunakan sebagai ELEMENT dan nilai position-nya adalah 0.
     */
    private $elements = array();

    /**
     * Cara cepat untuk mendapatkan informasi jumlah property $elements.
     * Nilai pada properti ini didefinisikan saat __construct().
     */
    public $length = 0;

    /**
     * Internal only. Properti ini digunakan oleh developer saat debugging.
     */
    public $debug = false;

    /**
     * Scope pencarian oleh method find(). Secara default jQuery mencari area
     * pencarian hanya descendents. Ketika object ini BARU di-instance, maka
     * scope pencarian akan diperluas menjadi keseluruhan $raw.
     *
     * Pilihan ini hanya bisa diubah oleh sistem secara otomatis, dengan opsi
     * yakni: 'raw', dan 'descendants'.
     */
    private static $find_scope = 'descendants';

    /**
     * Internal only. Property tempat penampungan hasil build regex oleh method
     * parseConditions().
     */
    public static $mask;

    /**
     * Construct object.
     *
     * Property $length didefinisikan disini.
     *
     * @param $raw string
     *   Data mentah html, lihat pada properti $raw.
     * @param $elements array
     *   Merupakan ELEMENTS, lihat pada Definisi FAQ.
     */
    function __construct($raw = null, $elements = null)
    {
        if (null !== $raw) {
            $this->raw = $raw;
            $this->length = 1;
            if (null === $elements) {
                self::$find_scope = 'raw';
            }
            elseif (is_array($elements)) {
                $this->elements = $elements;
                $this->length = count($elements);
            }
        }
    }

    /**
     * Mendapatkan nilai dari properti $raw.
     */
    public function getRaw()
    {
        return $this->raw;
    }

    /**
     * Mendapatkan nilai dari property $elements.
     */
    public function getElements()
    {
        if (empty($this->elements)) {
            return array('0' => $this->raw);
        }
        return $this->elements;
    }

    /**
     * Menemukan element-element dengan selector CSS.
     *
     * Membuat object baru dengan element-element
     * yang dicari berdasarkan selector CSS. Selector yang didukung terbatas,
     * untuk mengetahui selector yang didukung dapat melihat dokumentasi pada
     * homepage.
     *
     * Get the descendants of each element in the current set of matched elements,
     * filtered by a selector.
     * Reference:
     *   http://api.jquery.com/find/
     *   http://api.jquery.com/category/selectors/
     *
     * @param $selector string
     *   Selector CSS untuk mendapatkan element.
     *
     * Return
     *   Mengembalikan object parseHTML, baik object yang terdapat element
     *   (bila selector valid dan ditemukan) atau object kosong tanpa element
     *   (bila selector tidak valid atau hasil tidak ditemukan).
     */
    public function find($selector)
    {
        // Raw dapat bernilai null, terjadi jika find tidak menemukan element.
        // Bila hasil pencarian kosong, atau selector tidak valid, maka
        // Kita perlu me-return Object kosong, agar mendukung thread method oleh
        // user, sehingga tidak error.
        // Contoh: $html->find('invalid selector')->attr('name');
        if (is_null($this->raw)) {
            return new static::$class_name;
        }
        // Dapatkan element.
        $elements = $this->getElements();
        // Translate selector.
        $multi_selector = $this->translateSelector($selector);

        if (!$multi_selector) {
            return new static::$class_name;
        }
        // Buat penyimpanan hasil.
        $storage = array();

        // Simpan informasi raw dan descendents.
        $find_scope_init = self::$find_scope;

        while ($search_elements = array_shift($multi_selector)) {
            // Search.
            $result = $this->findElements($elements, $search_elements, $this->raw);
            // Nilai dari $result dapat null atau empty array, merge
            // jika ada value.
            if ($result) {
                $storage += $result;
            }
            // Untuk selector berikutnya, jika sejak awal property
            // $find_scope merupakan 'raw', maka perlu dikembalikan ke
            // 'raw', karena property $find_scope akan dipaksa ubah
            // oleh method ::findElementEach() ke 'descendents'.
            if ($find_scope_init == 'raw') {
                self::$find_scope = 'raw';
            }
        }
        if (!empty($storage)) {
            return new static::$class_name($this->raw, $storage);
        }
        return new static::$class_name;
    }

    /**
     * Mendapatkan keseluruhan html dari element pertama.
     *
     * Get the HTML contents of the first element in the set of matched elements.
     * Reference:
     * - http://api.jquery.com/html/
     */
    public function html()
    {
        // Kita hanya toleransi pada element yang pertama.
        return array_shift($this->getElements());
    }

    /**
     * Mendapatkan nilai text dari element tanpa tag html, termasuk element
     * descendent.
     *
     * Get the combined text contents of each element in the set of matched
     * elements, including their descendants.
     * Reference:
     * - http://api.jquery.com/text/
     */
    public function text()
    {
        return strip_tags($this->html());
    }

    /**
     * Mendapatkan informasi attribute dari element yang pertama.
     *
     * Get the value of an attribute for the first element in the set of
     * matched elements.
     * Reference:
     * - http://api.jquery.com/attr/
     *
     * param $name string
     *  Nama attribute yang ingin didapat value-nya.
     */
    public function attr($name)
    {
        $attributes = $this->extractAttributes($this->html(), true);
        return isset($attributes[$name]) ? $attributes[$name] : null;
    }

    /**
     *
     */
    public function prev($selector = null)
    {
        // Todo.
    }

    /**
     *
     */
    public function next($selector = null)
    {
        // Todo.
    }

    /**
     *
     */
    public function parent($selector = null)
    {
        // Todo.
    }

    /**
     *
     */
    public function parents($selector = null)
    {
        // Todo.
    }

    /**
     *
     */
    public function children($selector = null)
    {
        // Todo.
    }

    /**
     *
     */
    public function contents()
    {
        // Todo.
    }

    /**
     * Mereduksi element yang di-attach dari jamak menjadi satu.
     *
     * @param $index int
     *   Posisi dari element, dimana posisi pertama dimulai dari index 0.
     *
     * Reduce the set of matched elements to the one at the specified index.
     * Reference:
     * - http://api.jquery.com/eq/
     */
    public function eq($index)
    {
        // Todo: support for negative index.
        $elements = $this->getElements();
        $keys = array_keys($elements);
        if (isset($keys[$index]) && isset($elements[$keys[$index]])) {
            $position = $keys[$index];
            return new static::$class_name($this->raw, array($position => $elements[$position]));
        }
        else {
            return new static::$class_name;
        }
    }

    /**
     * Kode pada method ini gabungan dari ::_construct dan ::find
     * dengan penyesuaian.
     */
    public static function init($contents, $selector)
    {
        // Dapatkan element.
        $elements = [0 => $contents];

        // Translate selector.
        $multi_selector = self::translateSelector($selector);
        if (!$multi_selector) {
            return new static::$class_name;
        }
        $storage = array();
        $find_scope_init = self::$find_scope = 'raw';
        while ($search_elements = array_shift($multi_selector)) {
            // Search.
            $result = self::findElements($elements, $search_elements, $contents);
            // Nilai dari $result dapat null atau empty array, merge
            // jika ada value.
            if ($result) {
                $storage += $result;
            }
            // Untuk selector berikutnya, jika sejak awal property
            // $find_scope merupakan 'raw', maka perlu dikembalikan ke
            // 'raw', karena property $find_scope akan dipaksa ubah
            // oleh method ::findElementEach() ke 'descendents'.
            if ($find_scope_init == 'raw') {
                self::$find_scope = 'raw';
            }
        }
        if (!empty($storage)) {
            return new static::$class_name($contents, $storage);
        }
        return new static::$class_name;
    }

    /**
     * Melakukan trim dengan behaviour seperti html saat tampil di browser,
     * yakni lebih dari satu whitespace hanya akan dianggap satu whitespace.
     */
    public static function trimHtml($html)
    {
        $html = preg_replace('/\s\s+/', ' ', $html);
        (ctype_space($html) === false) or $html = '';
        return $html;
    }

    /**
     * Mendapatkan nama tag element.
     *
     * @param $element_html atau $starttag.

     * @return
     *   Lowercase dari nama tag.
     */
    public static function getTagName($start_tag)
    {
        $mask = '/^<(?P<tag>\w+)\s*[^>]*>/';
        if (preg_match($mask, $start_tag, $matches)) {
            return strtolower($matches['tag']);
        }
    }

    /**
     * Mencari element dengan berdasarkan attribute.
     *
     * Reference:
     * - http://www.w3.org/TR/xhtml1/#h-4.5
     * - http://stackoverflow.com/questions/13159180/set-attribute-without-value
     *
     * @param $attribute
     *   String attribute yang mau dicari, incasesensitive.
     *   Contoh: 'class', 'id',
     * @param $html
     *   String dengan format html.
     * @param $callback callable
     *   String atau array yang dapat dipanggil untuk melakukan tambahan filter.
     * @param $param_arr array
     *   Array berisi argument yang akan di-passing ke $callback.
     *
     * Return
     *   Mengembalikan "Array Elements Starttag"
     *   lihat pada Definisi FAQ.
     */
    public static function getElementByAttribute($attribute, $html, $callback = null, $param_arr = null)
    {
        // Set storage.
        $storage = array();
        // Validate.
        if (!self::validateAttributeElement($attribute) || strlen($html) === 0) {
            return $storage;
        }
        // Find string.
        $find = $attribute;
        $length = strlen($find);
        $scope = $html;
        $offset = 0;
        $distance = stripos($scope, $find);

        while ($distance !== false) {
            $position = $distance + $offset;
            // Sebelum disimpan ke storage, maka validasi beberapa hal.
            // Karakter sebelumnya harus whitespace.
            $pch = (isset($html[$position - 1])) ? $html[$position - 1] : false;
            if ($pch && ctype_space($pch)) {
                // Cek apakah posisi pointer dari ditemukannya attribute itu
                // berada diantara start tag html.
                if (self::validateInsideStartTag($position, $html)) {
                    // Cek apakah karakter pertama setelah
                    // karakter < pada element adalah string valid.
                    $prefix = substr($html, 0, $position);
                    $suffix = substr($html, $position);
                    $starttag_lt_position = strrpos($prefix, '<');
                    $starttag_rt_position = $position + strpos($suffix, '>') + strlen('>');
                    $start_tag = substr($html, $starttag_lt_position, $starttag_rt_position - $starttag_lt_position);
                    if (self::validateStartTag($start_tag)) {
                        // Validasi selesai, maka masukkan data ke $storage.
                        // Tapi tunggu dulu, karena method ini juga dapat digunakan
                        // oleh method lain, maka cek dulu apakah ada custom filter.
                        if (is_callable($callback)) {
                            // Insert our arguments.
                            $args = $param_arr;
                            is_array($args) or $args = array();
                            $args[] = $start_tag;
                            // Check.
                            if ($action = call_user_func_array($callback, $args)) {
                                // True then insert.
                                $storage[$starttag_lt_position] = $start_tag;
                                // What do you want after true.
                                if (isset($action['break']) && $action['break']) {
                                    break;
                                }
                            }
                        }
                        // Gak ada custom filter bro, jadi masukin aja dah..
                        else {
                            $storage[$starttag_lt_position] = $start_tag;
                        }
                    }
                }
            }
            // Ubah offset dan scope.
            $offset += $distance + $length;
            $scope = substr($html, $offset);
            $distance = stripos($scope, $find);
        }
        return $storage;
    }

    /**
     * Mencari element dengan custom filter berdasarkan attribute dan kondisinya.
     *
     * Sama sepert method getElementByAttribute() namun dengan fitur filter
     * seperti query sql. Method ini terinspirasi dari method pada
     * class parseCSV dan dilakukan pengembangan agar mirip dengan kebutuhan
     * selector CSS.
     *
     * Mengembalikan array dengan key merupakan posisi pointer
     * dan value merupakan starttag. Attribute yang dicari
     * juga bisa tanpa value (Meskipun tidak valid pada XHTML).
     *
     * Untuk mengubah starttag menjadi full element (termasuk content dan
     * endatag) gunakan method constructElements() atau constructElement().
     *
     * Reference:
     *  - https://github.com/parsecsv/parsecsv-for-php
     *
     * @param $conditions string
     *   Syntax seperti sql, berupa kalimat logika untuk pencarian.
     *   Contoh:
     *    - $conditions = 'title equals Mari Kemari';
     *      Berarti mencari element dengan title sama dengan 'Mari Kemari'.
     *    - $conditions = 'title equals "Mari Kemari"';
     *      Berarti mencari element dengan title sama dengan 'Mari Kemari'.
     *    - $conditions = "title equals 'Mari Kemari'";
     *      Berarti mencari element dengan title sama dengan 'Mari Kemari'.
     *    - $conditions = "class contains 'first'";
     *      Berarti mencari element dengan attribute class mengandung
     *      kata 'first'.
     *    - $conditions = "id = 'form' OR method = GET";
     *      Berarti mencari element dengan attribute id sama dengan 'form' ATAU
     *      juga memiliki attribute method dengan nilai sama dengan 'GET'.
     *    - $conditions = "data-length > 500 AND data-length < 2000";
     *      Berarti mencari element dengan attribute data-length lebih dari 500
     *      DAN kurang dari 2000.
     *
     *   Operator yang tersedia dapat dilihat pada method
     *   parseConditions().
     *
     * @param $html
     *   String dengan format html.
     *
     * Return
     *   Mengembalikan "Array Elements Starttag"
     *   lihat pada Definisi FAQ.
     *   Contoh array yang dihasilkan dengan conditons yang dicari
     *   adalah 'class contains word a OR class contains word x':
     *
     *     array(
     *       '5' => '<body class="a b">',
     *       '25' => '<img class="x y">',
     *       '83' => '<div class="a x">',
     *     );
     *
     */
    public static function getElementByAttributes($conditions, $html)
    {
        $elements = array();
        // Validate.
        $conditions = trim($conditions);
        if (empty($html) || empty($conditions)) {
            return $elements;
        }
        $attributes = self::parseConditions($conditions);
        foreach($attributes as $attribute) {
            $elements += self::getElementByAttribute($attribute, $html);
        }
        // Filtering.
        foreach($elements as $position => $element) {
            $attributes = self::extractAttributes($element);
            if (!self::_validateAttributeConditions($attributes, $conditions)) {
                unset($elements[$position]);
            }
        }
        return $elements;
    }

    /**
     * Mencari element berdasarkan value dari attribute id.
     *
     * Method ini dibuat untuk efisiensi pencarian element alih-alih
     * menggunakan method getElementByAttributes() karena method ini
     * me-reduce looping.
     *
     * Ilustrasi: daripada mencari element dengan cara seperti ini:
     *
     *   $this->getElementByAttributes('id = somevalue', $html)
     *
     * sebaiknya gunakan cara ini:
     *
     *   $this->getElementById('somevalue', $html);
     *
     *
     * Mengembalikan array dengan key merupakan posisi pointer
     * dan value merupakan starttag. Attribute yang dicari
     * juga bisa tanpa value (Meskipun tidak valid pada XHTML).
     *
     * Untuk mengubah starttag menjadi full element (termasuk content dan
     * endatag) gunakan method constructElements() atau constructElement().
     *
     * @param $value string
     *   Value dari attribute id yang akan dicari
     * @param $html
     *   String dengan format html.
     *
     * Return
     *   Mengembalikan "Array Elements Starttag"
     *   lihat pada Definisi FAQ.
     *   Id seharusnya hanya ada satu tiap element pada satu dokumen html.
     *   Namun jika terdapat banyak element berattribute id dengan value
     *   yang sama, maka kita hanya akan mengambil element yang pertama.
     *   Contoh:
     *
     *     array(
     *       '65' => '<div id="somevalue" class="a">',
     *     );
     *
     */
    public static function getElementById($value, $html)
    {
        $callback = 'self::_getElementById';
        $param_arr = array($value);
        return self::getElementByAttribute('id', $html, $callback, $param_arr);
    }

    /**
     * Mencari element berdasarkan value dari attribute class.
     *
     * Method ini dibuat untuk efisiensi pencarian element alih-alih
     * menggunakan method getElementByAttributes() karena method ini
     * me-reduce looping.
     *
     * Ilustrasi: daripada mencari element dengan cara seperti ini:
     *
     *   $this->getElementByAttributes('class ~= somevalue', $html)
     *
     * sebaiknya gunakan cara ini:
     *
     *   $this->getElementByClass('somevalue', $html);
     *
     *
     * Mengembalikan array dengan key merupakan posisi pointer
     * dan value merupakan starttag. Attribute yang dicari
     * juga bisa tanpa value (Meskipun tidak valid pada XHTML).
     *
     * Untuk mengubah starttag menjadi full element (termasuk content dan
     * endatag) gunakan method constructElements() atau constructElement().
     *
     * @param $value string
     *   Value dari attribute class yang akan dicari.
     *   Dapat menggunakan contitions. Contoh:
     *    - "cinta"
     *      Mencari class yang terdapat kata cinta.
     *    - "cinta AND love"
     *      Mencari class yang terdapat kata cinta DAN love.
     *    - "cinta OR love"
     *      Mencari class yang terdapat kata cinta ATAU love.
     *
     * @param $html
     *   String dengan format html.
     *
     * Return
     *   Mengembalikan "Array Elements Starttag"
     *   lihat pada Definisi FAQ.
     *   Contoh:
     *
     *     array(
     *       '65' => '<div id="primary" class="somevalue">',
     *       '230' => '<div id="secondary" class="somevalue">',
     *     );
     *
     */
    public static function getElementByClass($value, $html)
    {
        $callback = 'self::_getElementByClass';
        $param_arr = array($value);
        return self::getElementByAttribute('class', $html, $callback, $param_arr);
    }

    /**
     * Mencari element berdasarkan tagname.
     *
     * Mengembalikan array dengan key merupakan posisi pointer
     * dan value merupakan starttag. Attribute yang dicari
     * juga bisa tanpa value (Meskipun tidak valid pada XHTML).
     *
     * Untuk mengubah starttag menjadi full element (termasuk content dan
     * endatag) gunakan method constructElements() atau constructElement().
     *
     * @param $tag
     *   String tag yang mau dicari, incasesensitive.
     *   Contoh: 'a', 'img',
     * @param $html
     *   String dengan format html.
     * @param $callback callable
     *   String atau array yang dapat dipanggil untuk melakukan tambahan filter.
     * @param $param_arr array
     *   Array berisi argument yang akan di-passing ke $callback.
     *
     * Return
     *   Mengembalikan "Array Elements Starttag"
     *   lihat pada Definisi FAQ.
     *   Contoh array yang dihasilkan dengan tag yang dicari
     *   adalah a:
     *   array(
     *     '5' => '<a class="a">',
     *     '25' => '<a class="b">',
     *     '83' => '<a class="c">',
     *     '253' => '<a class>',
     *   );
     */
    public static function getElementByTag($tag, $html, $callback = null, $param_arr = null)
    {
        $tag = trim($tag);
        // Set storage.
        $storage = array();
        // Validate.
        // if (self::validateTagElement($tag) === false) {
            // return $storage;
        // }
        // Find string.
        $find = '<' . $tag;
        $length = strlen($find);
        $scope = $html;
        $offset = 0;
        $distance = stripos($scope, $find);
        while ($distance !== false) {
            $position = $distance + $offset;
            // Sebelum disimpan ke storage, maka validasi beberapa hal.
            // Karakter sebelumnya harus < dan karakter sesudahnya harus
            // whitespace atau >.
            $nch = (isset($html[$position + $length])) ? $html[$position + $length] : false;
            $pch = (isset($html[$position - 1])) ? $html[$position - 1] : false;
            if ($nch && (ctype_space($nch) || $nch == '>')) {
                // Cek apakah posisi pointer dari ditemukannya attribute itu
                // berada diantara start tag html.
                $_position = $position + 1;
                if ($_position && self::validateInsideStartTag($_position, $html)) {
                    $suffix = substr($html, $_position);
                    $starttag_lt_position = $position;
                    $starttag_rt_position = strpos($html, '>', $position) + strlen('>');
                    $start_tag = substr($html, $starttag_lt_position, $starttag_rt_position - $starttag_lt_position);
                    // Validasi selesai, maka masukkan data ke $storage.
                    // Tapi tunggu dulu, karena method ini juga dapat digunakan
                    // oleh method lain, maka cek dulu apakah ada custom filter.
                    if (is_callable($callback)) {
                        // Insert our arguments.
                        $args = $param_arr;
                        is_array($args) or $args = array();
                        $args[] = $start_tag;
                        // Check.
                        if ($action = call_user_func_array($callback, $args)) {
                            // True then insert.
                            $storage[$starttag_lt_position] = $start_tag;
                            // What do you want after true.
                            if (isset($action['break']) && $action['break']) {
                                break;
                            }
                        }
                    }
                    // Gak ada custom filter bro, jadi masukin aja dah..
                    else {
                        $storage[$starttag_lt_position] = $start_tag;
                    }
                }
            }

            // Ubah offset dan scope.
            $offset += $distance + $length;
            $scope = substr($html, $offset);
            $distance = stripos($scope, $find);
        }
        return $storage;
    }

    /**
     * Memecah element_html menjadi starttag, contents, dan endtag
     *
     * @param $element_html string
     *   Element html lengkap dengan starttag, contents, dan endtag (kecuali
     *   void element). Element harus sudah trim dari whitespace atau akan
     *   gagal.
     *
     * Return
     *   Mengembalikan array dengan key index, dimana:
     *    - key = 0, merupakan starttag, atau false jika not found or failed,
     *    - key = 1, merupakan contents, atau false jika not found or failed,
     *    - key = 2, merupakan endtag, atau false jika not found or failed.
     *   Info element ini dapat dengan mudah diparsing dengan fungsi list().
     */
    public static function parseElement($element_html, $return = null)
    {
        $starttag = $contents = $endtag = false;
        // Dapatkan starttag dengan regex.
        $mask = '/^<(?P<tag>\w+)\s*[^>]*>/';
        preg_match($mask, $element_html, $matches);
        if (preg_match($mask, $element_html, $matches)) {
            // Dapatkan contents dan endtag dengan strpos, strlen, dan substr.
            $starttag = $matches[0];
            $tag = $matches['tag'];
            $_endtag = '</' . $tag . '>';
            if ($distance = strripos($element_html, $_endtag)) {
                if ($endtag = substr($element_html, $distance)) {
                    $contents = substr($element_html, strlen($starttag), strlen($endtag) * -1);
                }
            }
        }
        if (null === $return) {
            return array($starttag, $contents, $endtag);
        }
        switch ($return) {
            case 'starttag':
                return $starttag;

            case 'contents':
                return $contents;

            case 'endtag':
                return $endtag;
        }
    }

    /**
     * Mengubah selector css menjadi array untuk proses filtering.
     *
     * Array yang dihasilkan akan menjadi susunan seperti ini.
     *
     * Multi selector
     *  - Selector
     *     - Elements descendent
     *        - Element
     *           - Direct
     *           - Tag
     *           - Attributes
     *              - Attribute 1
     *              - Attribute 2
     *              - Attribute 3
     *
     * Contoh paling ekstrem:
     *
     *   $selector = 'div.class1.class2 a, #someid.class3.class4 > img[title][href="\\/a"]';
     *
     *   array(
     *     // First Selector.
     *     0 => array(
     *       // Elements descendents.
     *       0 => array(
     *         'direct' => false
     *         'tag' => array(
     *           0 => 'div'
     *         ),
     *         'attributes' => array(
     *           0 => array(
     *             'name' => 'class'
     *             'operator' => '~~='
     *             'value' => 'class1 class2'
     *           ),
     *         ),
     *       ),
     *       1 => array(
     *         'direct' => false
     *         'tag' => array(
     *           0 => 'a'
     *         ),
     *         'attributes' => array(
     *         ),
     *       ),
     *     ),
     *
     *     // Second Selector.
     *     1 => array(
     *       // Elements descendents.
     *       0 => array(
     *         'direct' => false
     *         'tag' => array(
     *         ),
     *         'attributes' => array(
     *           0 => array(
     *             'name' => 'id'
     *             'operator' => '='
     *             'value' => 'someid'
     *           ),
     *           1 => array(
     *             'name' => 'class'
     *             'operator' => '~~='
     *             'value' => 'class3 class4'
     *           ),
     *         ),
     *       ),
     *       1 => array(
     *         'direct' => true
     *         'tag' => array(
     *           0 => 'img'
     *         ),
     *         'attributes' => array(
     *           0 => array(
     *             'name' => 'title'
     *             'operator' =>
     *             'value' =>
     *           ),
     *           1 => array(
     *             'name' => 'href'
     *             'operator' => '='
     *             'value' => '/a'
     *           ),
     *         ),
     *       ),
     *     ),
     *   )
     */
    public static function translateSelector($selector)
    {
        $string = trim($selector);
        $string_length = strlen($string);
        $meta_characters = '!"#$%&\'()*+,./:;<=>?@[\\]^`{|}~';
        $last = substr($string, -1, 1);
        $first = substr($string, 0, 1);

        // 1st Validation.
        // Krakter terakhir tidak boleh meta karakter kecuali karakter ].
        if ($last != ']' && strpos($meta_characters, $last) !== false) {
            return false;
        }
        // Karakter pertama jikapun meta character, hanya boleh antara . # [
        elseif (strpos($meta_characters, $first) !== false && !in_array($first, array('#', '.', '['))) {
            return false;
        }
        // Categorize charachter by type to easy us.
        $characters = array();
        for ($x = 0; $x < $string_length; $x++) {
            $char = $string[$x];
            $type = 'std';
            if ($char == '\\' && isset($string[$x + 1]) && strpos($meta_characters, $string[$x + 1]) !== false) {
                $char = $string[++$x];
            }
            elseif (strpos($meta_characters, $char) !== false) {
                $type = 'meta';
            }
            elseif (ctype_space($char) !== false) {
                $type = 'space';
            }
            $characters[] = array(
                $type => $char,
            );
        }

        // Build flag.
        $step = 'init';
        $attribute_name = '';
        $attribute_operator = '';
        $attribute_value = '';
        $quote = '';
        $tag = '';
        $register_selector = false;
        $register_elements = false;
        $register_element = false;
        $is_last = false;
        $selector = $elements = $_elements = array();
        $element = $_element = array('direct' => false, 'tag' => array(), 'attributes' => array());
        $x = 0;
        $string_length = count($characters);

        // Walking.
        while ($character = array_shift($characters)) {
            ($x != $string_length - 1) or $is_last = true;
            switch ($step) {
                case 'init':
                    if (isset($character['std'])) {
                        $tag .= $character['std'];
                        $step = 'build tag';
                        if ($is_last) {
                            $register_element = true;
                            $register_elements = true;
                            $register_selector = true;
                        }
                    }
                    elseif (isset($character['meta'])) {
                        switch ($character['meta']) {
                            case ',':
                                $register_elements = true;
                                $register_selector = true;
                                break;

                            case '>':
                                $element['direct'] = true;
                                break;

                            case '#':
                                $attribute_name = 'id';
                                $attribute_operator = '=';
                                $step = 'build value';
                                break;

                            case '.':
                                $attribute_name = 'class';
                                $attribute_operator = '~=';
                                $step = 'build value';
                                break;

                            case '[':
                                $step = 'brackets build name';
                                break;
                        }
                    }
                    // elseif (isset($character['space'])) {
                        // $register_elements = true;
                    // }
                    break;

                case 'brackets build name':
                    if (isset($character['std'])) {
                        $attribute_name .= $character['std'];
                    }
                    elseif (isset($character['meta'])) {
                        switch ($character['meta']) {
                            case ']':
                                $register_element = true;
                                if ($is_last) {
                                    $register_elements = true;
                                    $register_selector = true;
                                }
                                break;

                            default:
                                $attribute_operator = $character['meta'];
                                $step = 'brackets build operator';
                        }
                    }
                    break;

                case 'brackets build operator':
                    if (isset($character['std'])) {
                        $attribute_value .= $character['std'];
                        $step = 'brackets build value';
                    }
                    elseif (isset($character['meta'])) {
                        switch ($character['meta']) {
                            case '"':
                            case "'":
                                $quote = $character['meta'];
                                $step = 'brackets build value';
                                break;

                            case ']':
                                $register_element = true;
                                if ($is_last) {
                                    $register_elements = true;
                                    $register_selector = true;
                                }
                                break;

                            default:
                                $attribute_operator .= $character['meta'];
                        }
                    }
                    break;

                case 'brackets build value':
                    if (isset($character['std'])) {
                        $attribute_value .= $character['std'];
                    }
                    elseif (isset($character['meta']) && in_array($character['meta'], array('"', "'")) && $character['meta'] != $quote) {
                        $attribute_value .= $character['meta'];
                    }
                    elseif (isset($character['meta']) && $character['meta'] == ']') {
                        $register_element = true;
                        if ($is_last) {
                            $register_elements = true;
                            $register_selector = true;
                        }
                    }
                    elseif (isset($character['meta'])) {
                        $attribute_value .= $character['meta'];
                    }
                    break;

                case 'build value':
                    if (isset($character['std'])) {
                        $attribute_value .= $character['std'];
                        if ($is_last) {
                            $register_element = true;
                            $register_elements = true;
                            $register_selector = true;
                        }
                    }
                    elseif (isset($character['space'])) {
                            $register_element = true;
                            $register_elements = true;
                    }
                    elseif (isset($character['meta'])) {
                        // Khusus class, maka ada perlakuan khusus.
                        if ($character['meta'] == '.' && $attribute_name == 'class') {
                            $attribute_value .= ' ';
                            $attribute_operator = '~~=';
                        }
                        // Khusus class, maka ada perlakuan khusus.
                        elseif ($character['meta'] == ',') {
                            $register_element = true;
                            $register_elements = true;
                            $register_selector = true;
                        }
                        else {
                            $register_element = true;
                        }

                    }
                    break;

                case 'build tag':
                    if (isset($character['std'])) {
                        $tag .= $character['std'];
                        if ($is_last) {
                            $register_element = true;
                            $register_elements = true;
                            $register_selector = true;
                        }
                    }
                    elseif (isset($character['space'])) {
                        $register_element = true;
                        $register_elements = true;
                    }
                    elseif (isset($character['meta'])) {
                        switch ($character['meta']) {
                            case ',':
                                $register_element = true;
                                $register_elements = true;
                                $register_selector = true;
                                break;

                            case '#':
                                $attribute_name = 'id';
                                $attribute_operator = '=';
                                $step = 'build value';
                                break;

                            case '.':
                                $attribute_name = 'class';
                                $attribute_operator = '~=';
                                $step = 'build value';
                                break;

                            case '[':
                                $step = 'brackets build name';
                                break;
                        }
                    }
                    break;
            }
            if ($register_element) {
                empty($tag) or $element['tag'][] = $tag;
                if ((empty($attribute_name) && empty($attribute_operator) && empty($attribute_value)) == false) {
                    $element['attributes'][] = array(
                        'name' => $attribute_name,
                        'operator' => $attribute_operator,
                        'value' => $attribute_value,
                    );
                }
                $register_element = false;
                $attribute_name = '';
                $attribute_operator = '';
                $attribute_value = '';
                $quote = '';
                $tag = '';
                if (isset($character['meta'])) {
                    switch ($character['meta']) {
                        case '#':
                            $attribute_name = 'id';
                            $attribute_operator = '=';
                            $step = 'build value';
                            break;

                        case '.':
                            $attribute_name = 'class';
                            $attribute_operator = '~=';
                            $step = 'build value';
                            break;

                        case ']':
                            // Jika karakter setelahnya adalah spasi, maka daftarkan ke
                            // elements.
                            $step = 'init';
                            if (isset($string[$x + 1]) && ctype_space($string[$x + 1])) {
                                $register_elements = true;
                            }
                            break;

                        case '[':
                            $step = 'brackets build name';
                            break;
                    }
                }
            }
            if ($register_elements) {
                // 2nd Validation.
                // $('div[tempe~=bacem]div'); Selector valid oleh jquery.
                // $('div[tempe~=bacem]a'); Selector tidak error regex tapi hasilnya null.
                // Sehingga jika terdapat lebih dari satu tag, maka kita anggap selector
                // tidak valid.
                empty($element['tag']) or $element['tag'] = array_unique($element['tag']);
                if (is_array($element['tag']) && count($element['tag']) > 1) {
                    return false;
                }
                $elements[] = $element;
                $register_elements = false;
                $element = $_element;
                $step = 'init';
            }
            if ($register_selector) {
                $selector[] = $elements;
                $elements = $_elements;
                $register_selector = false;
                $step = 'init';
            }
            $x++;
        }
        return $selector;


    }

    /**
     * Mengembalikan attribute dari $element_html
     * berupa array sederhana dimana key merupakan nama attribute
     * dan value merupakan nilai dari attribute. Nama attribute
     * yang di-return selalu lower case.
     *
     * Ketentuan dalam extract attribute yakni:
     *  1) nama attribute incase-sensitive
     *  2) Jika nama attribute lebih dari satu,
     *     maka yang akan dianggap adalah nama attribute yang pertama
     *
     *
     * @param $element_html string
     *   Element atau start tag. Start tag harus diawali dengan karakter
     *   < dan diakhir dengan >.
     *   Contoh:
     *   ```html
     *     <a title="mytitle" href="link">
     *   ```
     *
     * @param $validate bool
     *   If set true, any invalid attribute name will be removed.
     *
     * Return
     * Mengembalikan associative array dengan key adalah nama attribute,
     * dan value adalah value attribute.
     * Contoh paling ekstrem, misalnya kita memiliki start tag sbb:
     *
     *   <a "mengapa" tempe  'agama'="" id="roji" 965="cintakita"
     *   duhai= class="anto" dengan cinta="kita" cinta="bisa gila" yoyo=ok>
     *
     * Hasil yang akan didapat adalah sbb:
     *
     *   array(
     *     '"mengapa"' => null,
     *     'tempe' => null,
     *     "'agama'" => '',
     *     'id' => 'roji',
     *     '965' => 'cintakita',
     *     'duhai' => 'class="anto"',
     *     'dengan' => null,
     *     'cinta' => 'kita',
     *     'yoyo' => 'ok',
     *   );
     *
     * Hasil yang akan didapat jika dilakukan validasi adalah sbb:
     *
     *   array(
     *     'tempe' => null,
     *     'id' => 'roji',
     *     '965' => 'cintakita',
     *     'duhai' => 'class="anto"',
     *     'dengan' => null,
     *     'cinta' => 'kita',
     *     'yoyo' => 'ok',
     *   );
     *
     */
    public static function extractAttributes($element_html, $validate = false)
    {
        $attributes = array();
        // Validasi element_html.
        $mask = '/^<\w+\s*(?P<attributes>[^>]*)>/';
        if (preg_match($mask, $element_html, $matches)) {
            $string = rtrim($matches['attributes']);
            $string_length = strlen($string);
            $string_last = $string_length - 1;
            $step = 'init';
            $name = '';
            $value = '';
            $quote = '';
            $register = false;

            // Walking.
            for ($x = 0; $x < $string_length; $x++) {
                $char = $string[$x];
                switch ($step) {
                    case 'init':
                        $name .= $char;
                        $step = 'build_name';
                        break;

                    case 'build_name':
                        if ($char == '=') {
                            $step = 'check_quote';
                        }
                        elseif (ctype_space($char)) {
                            $value = null;
                            $register = true;
                        }
                        else {
                            $name .= $char;
                        }
                        break;

                    case 'check_quote':
                        if ($char == '"' || $char == "'") {
                            $step = 'build_value';
                            $quote = $char;
                        }
                        elseif (ctype_space($char)) {
                            break;
                        }
                        else {
                            $value = $char;
                            $quote = '';
                            $step = 'build_value';
                        }
                        break;

                    case 'build_value':
                        if (empty($quote) && ctype_space($char)) {
                            $register = true;
                        }
                        elseif (empty($quote) && $x === $string_last) {
                            $value .= $char;
                            $register = true;
                        }
                        elseif ($char == $quote) {
                            $register = true;
                        }
                        else {
                            $value .= $char;
                        }
                        break;
                }
                if ($register) {
                    empty($name) or $name = strtolower($name);
                    if (!empty($name) && !isset($attributes[$name])) {
                        $attributes[$name] = $value;
                    }
                    $register = false;
                    $name = '';
                    $value = '';
                    $quote = '';
                    $step = 'build_name';
                }
            }
        }
        if (!empty($attributes) && $validate) {
            $validates = self::validateAttributeElement(array_keys($attributes));
            foreach($validates as $validate) {
                list($key, $result) = $validate;
                if (!$result) {
                    unset($attributes[$key]);
                }
            }
        }
        return $attributes;
    }

    /**
     * Validation of tag of void element.
     *
     *  > Void elements only have a start tag; end tags must not be specified
     *  > for void elements.
     *  >
     *  Reference: http://www.w3.org/TR/html-markup/syntax.html#void-element
     *
     */
    public static function validateTagVoidElement($tag)
    {
        $tags = '
            |area|base|br|col|command|embed|hr|img|input|keygen|link|meta|param|
            |source|track|wbr|
        ';
        return stristr($tags, '|' . $tag .'|') !== false;
    }

    /**
     * Boolean. Validation of tag of element.
     *
     *  > Tags are used to delimit the start and end of elements in markup.
     *  > Elements have a start tag to indicate where they begin.
     *  > Non-void elements have an end tag to indicate where they end.
     *  >
     *  > Tag names are used within element start tags and end tags to give
     *  > the element’s name. HTML elements all have names that only use
     *  > characters in the range 0–9, a–z, and A–Z.
     *  >
     *  Reference:
     *   - http://www.w3.org/TR/html-markup/syntax.html#tag-name
     *   - http://www.w3.org/TR/html-markup/elements.html
     *
     * @param $tag string
     *   Tag Name yang akan divalidasi.
     */
    public static function validateTagElement($tag)
    {
        $tags = '
            |a|abbr|address|area|article|aside|audio|
            |b|base|bdi|bdo|blockquote|body|br|button|
            |canvas|caption|cite|code|col|colgroup|command|
            |datalist|dd|del|details|dfn|div|dl|dt|
            |em|embed|
            |fieldset|figcaption|figure|footer|form|frameset|frame|
            |h1|h2|h3|h4|h5|h6|head|header|hgroup|hr|html|
            |i|iframe|img|input|ins|
            |kbd|keygen|
            |label|legend|li|link|
            |map|mark|menu|meta|meter|
            |nav|noscript|
            |object|ol|optgroup|option|output|
            |p|param|pre|progress|
            |q|
            |rp|rt|ruby|
            |s|samp|script|section|select|small|source|span|strong|style|
            |sub|summary|sup|
            |table|tbody|td|textarea|tfoot|th|thead|time|title|tr|track|
            |u|ul|
            |var|video|
            |wbr|
        ';
        return stristr($tags, '|' . $tag .'|') !== false;
    }

    /**
     * Boolean. Validation of start tag of element.
     *
     * @param $start_tag
     *   Example: <div id="main-content">
     */
    public static function validateStartTag($start_tag)
    {
        $mask = '/^<(?P<tag>\w+)\s*[^>]*>$/';
        if (preg_match($mask, $start_tag, $matches)) {
            return self::validateTagElement($matches['tag']);
        }
        return false;
    }

    /**
     * Validation of attribute name of element.
     *
     *  > Attribute names must consist of one or more characters
     *  > other than the space characters, U+0000 null,
     *  > """, "'", ">", "/", "=", the control characters,
     *  > and any characters that are not defined by Unicode.
     *  >
     *  Reference: http://www.w3.org/TR/html-markup/syntax.html#syntax-attributes
     *
     * @param $names mixed
     *   The name of attribute (string) or the index array contains
     *   name of attribute.
     *
     * Return
     *   Jika argument adalah string, maka akan mengembalikan boolean.
     *   Jika argument adalah array, maka tiap value array akan di-expand
     *   menjadi array dengan 2 value dimana key 0 adalah nama attribute, dan
     *   key 1 adalah hasil validasi.
     */
    public static function validateAttributeElement($names)
    {
        $string = is_string($names);
        // Todo: Add support for Unicode characters.
        $names = (array) $names;
        $forbidden = array('"', "'", '>', '/', '=');
        $preg_quote = function ($var) {
            return preg_quote($var, '/');
        };
        $forbidden = array_map($preg_quote, $forbidden);
        $forbidden[] = '\\s';
        $forbidden = implode('|', $forbidden);
        foreach($names as $key => &$name) {
            $name = array(
                $name,
                !preg_match('/' . $forbidden . '/', $name),
            );
        }
        return $string ? $names[0][1] : $names;
    }

    /**
     * Boolean. Validasi posisi pointer apakah berada didalam tag html.
     *
     * Posisi pointer biasanya didapat dari hasil fungsi strpos()
     * dalam dokumen html.
     *
     * Contoh: Misalnya karakter pipe "|" berarti posisi pointer.
     *
     *  1. ... title="bla bla"> My name is | Budi Anduk </a> ...
     *     Pada contoh diatas maka posisi pointer tidak berada di dalam
     *     start tag, sehingga method ini akan return false.
     *  2. <div title="main" | class="red"> My name is Budi Anduk </div>
     *     Pada contoh diatas maka posisi pointer berada di dalam
     *     start tag, sehingga method ini akan return true.
     *
     * @param $position
     *   Posisi pointer dari awal string. Biasanya didapat dari fungsi strpos().
     * @param $html
     *   String dengan format html.
     */
    public static function validateInsideStartTag($position, $html)
    {
        $result = '';
        // Jika kita bergerak mundur dari $position, maka
        // karakter < harus lebih dahulu ditemukan daripada karakter >
        $walk = $position;
        $lt = false;
        $gt = false;
        $temp = $walk;
        while (isset($html[--$walk])) {
            if ($html[$walk] == '<') {
                $lt = $walk;
                break;
            }
        }
        $walk = $position;
        while (isset($html[--$walk])) {
            if ($html[$walk] == '>') {
                $gt = $walk;
                break;
            }
        }
        // Khusus tag yang berada pada AWAL string
        if ($lt === 0 && $gt === false) {
            $result .= 1;
        }
        else {
            $result .= $gt < $lt ? '1' : '0';
        }

        // Jika kita bergerak maju dari $position, maka
        // karakter > harus lebih dahulu ditemukan daripada karakter <
        $walk = $position;
        $lt = false;
        $gt = false;
        while (isset($html[++$walk])) {
            if ($html[$walk] == '<') {
                $lt = $walk;
                break;
            }
        }
        $walk = $position;
        while (isset($html[++$walk])) {
            if ($html[$walk] == '>') {
                $gt = $walk;
                break;
            }
        }
        // Khusus tag yang berada pada AKHIR string.
        if ($lt === false && is_int($gt)) {
            $result .= 1;
        }
        else {
            $result .= $gt < $lt ? '1' : '0';
        }
        return strpos($result, '0') === false;
    }

    /**
     * Menemukan hasil element (satu atau jamak) dengan pencarian tertentu.
     *
     * @param $elements array
     *   Informasi element dimana key merupakan posisi pointer dan value
     *   merupakan element (lengkap dengan startag, contents, dan endtag
     *   kecuali void element).
     *   Contoh:
     *
     *     array(
     *       '241' => '<ul class="topnav"><li>Item A1</li></ul>',
     *       '618' => '<ul class="topnav"><li>Item B1</li></ul>',
     *     );
     *
     * @param $search_elements array
     *   Array yang berisi informasi pencarian per satu selector. Variable
     *   ini didapat dari hasil method "translateSelector()".
     *
     * @param $html string
     *   Data mentah keseluruhan dokumen html.
     *
     * Return
     *   Mengembalikan array seperti parameter $elements yang mana selector
     *   telah berhasil mendapatkan element yang diinginkan.
     */
    protected static function findElements($elements, $search_elements, $html)
    {
        $storage = array();
        foreach ($elements as $position => $element) {
            $result = self::findElementEach($position, $element, $search_elements, $html);
            if ($result) {
                $storage += $result;
            }
        }
        return $storage;
    }

    /**
     * Menemukan hasil element (satu atau jamak) dengan pencarian tertentu.
     *
     * @param $position int
     *   jarak element dari awal string $raw (idem dengan strpos).
     * @param $element string
     *   Element html lengkap dengan startag, contents, dan endtag
     *   kecuali void element.
     * @param $search_elements array
     *   Variable pencarian per satu selector, merupakan hasil dari method
     *   translateSelector().
     * @param $html string
     *   Data mentah keseluruhan dokumen html.
     */
    protected static function findElementEach($position, $element, $search_elements, $html)
    {
        $storage = array();

        // Untuk scope = raw, maka itu sama dengan selector yang digunakan
        // saat initialize, contoh jQuery:
        // var $table = $(selector);
        // Untuk scope = descendants, maka itu sama dengan selector, pada
        // method .find(), contoh jQuery:
        // var $table = $(selector);
        // var $span = $table.find(selector);
        switch (self::$find_scope) {
            case 'descendants':
                list($starttag, $contents, $endtag) = self::parseElement($element);
                $scope = $contents;
                $offset = strlen($starttag);
                break;

            case 'raw':
                $scope = $html;
                $offset = 0;
                break;
        }

        // Mengambil satu pencarian element, dari kumpulan element yang akan dicari
        // secara descendet oleh variable $search_elements.
        if ($search_element = array_shift($search_elements)) {
            // Khusus selector direct seperti "ul > li", maka kita perlu
            // melakukan manipulasi element agar pencarian didapat.
            // Oleh karena itu kita mampir dulu ke method findElementEach_direct()
            // untuk nantinya akan kembali ke method ini.
            if ($search_element['direct']) {
                // Kembalikan lagi variable pencarian element ke
                // kumpulan pencarian element-element.
                array_unshift($search_elements, $search_element);
                // Oper ke method findElementEach_direct() untuk dilakukan
                // manipulasi.
                return self::findElementEach_direct($position, $element, $search_elements, $html);
            }

            // Mulai membedah dan mencari informasi pencarian element dengan tag
            // maupun dengan attribute.
            $tag = empty($search_element['tag']) ? '' : array_shift($search_element['tag']);
            $attributes = $search_element['attributes'];

            // Tiap informasi tag dan attribute yang didapat, akan ada method spesifik
            // yang akan digunakan.
            // Mulai mencari method yang tepat.
            if (!empty($tag)) {
                $callback = 'self::getElementByTag';
                $param_arr = array($tag, $scope);
                if (!empty($attributes)) {
                    $param_arr[] = 'self::getElementByAttributes';
                    $param_arr[] = array(self::buildConditions($attributes));
                }
            }
            else {
                $callback = 'self::getElementByAttributes';
                if (count($attributes) == 1) {
                    $attribute = array_shift($attributes);
                    switch ($attribute['name']) {
                        case 'id':
                            $callback = 'self::getElementById';
                            $param_arr = array($attribute['value'], $scope);
                            break;

                        case 'class':
                            $callback = 'self::getElementByClass';
                            $attribute['value'] = str_replace(' ', ' AND ', $attribute['value']);
                            $param_arr = array($attribute['value'], $scope);
                            break;

                        default:
                            if (empty($attribute['operator']) && empty($attribute['value'])) {
                                $callback = 'self::getElementByAttribute';
                                $param_arr = array($attribute['name'], $scope);
                            }
                            else {
                                $conditions = self::buildConditions(array($attribute));
                                $param_arr = array($conditions, $scope);
                            }
                    }
                }
                else {
                    $conditions = self::buildConditions($attributes);
                    $param_arr = array($conditions, $scope);
                }
            }

            // Method dan argument untuk dieksekusi telah didefinisikan,
            // dan siap dieksekusi.

            $results = call_user_func_array($callback, $param_arr);

            // Variable $result berisi informasi element-element berupa array
            // (atau array kosong jika tidak ditemukan) dimana
            // key merupakan posisi pointer relative terhadap scope element
            // dan value merupakan start tag.
            // Kita perlu menyesuaikan posisi pointer agar relative terhadap
            // keseluruhan dokumen html.
            self::addPosition($results, $position + $offset);
            // Kita juga perlu mengembangkan informasi element
            // dari awalnya hanya startag,
            // menjadi element lengkap yang terdiri dari starttag, contents,
            // endtag (kecuali void element).
            self::constructElements($results, $html);
            // Masukkan ke storage.
            $storage += $results;
        }

        // Paksa untuk ubah find_scope ke descendent, karena
        // untuk argument $search_elements berikutnya akan mencari
        // descendent. Untuk multi selector yang menggunakan koma, nanti
        // value ini akan dikembalikan ke raw yang diubah
        // kembali oleh method ::find().

        self::$find_scope = 'descendants';

        // Informasi variable pencarian element-element pada
        // $search_elements kini telah berkurang satu.
        // Jika hasil pencarian ternyata element jamak, sementara variable
        // pencarian ($search_elements) secara descendent masih ada,
        // maka proses akan recursive dimana proses dimulai lagi
        // ke method findElements() sampai variable pencarian habis.
        if ($search_elements) {
            return self::findElements($storage, $search_elements, $html);
        }
        // Finish simpan ke storage.
        return $storage;
    }

    /**
     * Mengakomodir pencarian dengan selector direct children element.
     *
     * Method ini akan memanipulasi variable $element yang mungkin awalnya
     * terdiri dari banyak nested element menjadi hanya satu saja.
     *
     * @see ::getElementChildren
     *
     */
    protected static function findElementEach_direct($position, $element, $search_elements, $html)
    {
        $storage = array();
        $childrens = self::getElementChildren($position, $element);
        list($starttag, $contents, $endtag) = self::parseElement($element);
        if ($childrens) {
            // Wajib mengganti direct menjadi false,
            // atau unlimited looping.
            $search_elements[0]['direct'] = false;
            // Mulai membuat pseudo element.
            foreach($childrens as $p => $children) {
                // Hitung jarak dari endtag parent ke starttag direct children.
                $space = $p - $position - strlen($starttag);
                $a = '';
                // Buat spasi sebagai pengganti jeda antara parent dan direct children.
                while ($space-- > 0) {
                    $a .= ' ';
                }
                $pseudo_element = $starttag . $a . $children . $endtag;
                // Oper kembali ke method findElementEach().
                $result = self::findElementEach($position, $pseudo_element, $search_elements, $html);
                if ($result) {
                    $storage += $result;
                }
            }
        }
        return $storage;
    }

    /**
     * Mengubah informasi array attribute menjadi conditions.
     *
     * Array attribute didapat dari hasil translate selector css, sementara
     * conditions akan digunakan sebagai argument pada method
     * getElementByAttribute().
     */
    protected static function buildConditions($attributes)
    {
        $implode = function ($var) {
            return implode(' ', $var);
        };
        $attributes = array_map($implode, $attributes);
        return implode(' AND ', $attributes);
    }

    /**
     * Mendapatkan element children nested tepat satu level didalam.
     *
     * @param $position int
     *   Posisi dari parameter $element ke awal string $raw.
     *
     * @param $element string
     *   Element html yang terdiri dari starttag, content, dan endtag,
     *   kecuali void element yang hanya terdiri dari starttag.
     *
     * @param $auto_expand bool
     *   Jika true, maka starttag yang didapat akan diexpand sehingga menjadi
     *   full element.
     *
     * Return
     *   Array satu dimensi, dimana keys merupakan "position", yakni
     *   jarak element dari awal string $raw (idem dengan strpos) dan value
     *   merupakan starttag atau element lengkap disesuaikan dengan parameter
     *   $auto_expand.
     *   Contoh:
     *
     *     $element =
     *       '<ul>
     *         <li><a>LINK 1</a></li>
     *         <li><a>LINK 2</a></li>
     *         <li><a>LINK 3</a></li>
     *       </ul>';
     *
     *   Hasil yang akan didapat adalah sebagai berikut:
     *
     *     $array = array(
     *       'x' => '<li>',
     *       'x' => '<li>',
     *       'x' => '<li>',
     *     );
     */
    protected static function getElementChildren($position, $element, $auto_expand = false)
    {
        // Parsing element.
        list($starttag, $contents, $endtag) = self::parseElement($element);
        // Khusus void element, tidak diperlukan tree.
        if (!$starttag || !$contents) {
            return;
        }
        $offset = strlen($starttag);
        $storage = array();
        $find_lt = $lt = '<';
        $find_rt = $rt = '>';
        $scope = $contents;
        $distance_lt = strpos($scope, $find_lt);
        while ($distance_lt !== false) {
            // Karakter setelah < harus alphabet.
            $char = substr($scope, $distance_lt + strlen($lt), 1);
            if (false == preg_match('/[a-zA-Z]/', $char)) {
                // Skip $lt ini, Cari lagi yang lebih valid.
                $offset += $distance_lt + strlen($lt);
                $scope = substr($element, $offset);
                $distance_lt = stripos($scope, $find_lt);
                continue;
            }
            // Ketemu tag <alphabet, maka ketemu element child.
            $child_starttag_lt_position = $distance_lt + $offset;
            $distance_rt = strpos($scope, $find_rt, $distance_lt);
            $child_starttag_rt_position = $distance_rt + $offset;
            $child_starttag = substr($element, $child_starttag_lt_position, $child_starttag_rt_position + strlen($rt) - $child_starttag_lt_position);
            self::constructElement($child_starttag_lt_position, $child_starttag, $element);
            // Update offset dan scope.
            $offset += $distance_lt + strlen($child_starttag);
            $scope = substr($element, $offset);
            if (!$auto_expand) {
                self::destructElement($child_starttag);
            }
            // Save dan...
            $storage[$child_starttag_lt_position] = $child_starttag;
            // Cari lagi.
            $distance_lt = stripos($scope, $find_lt);
        }
        self::addPosition($storage, $position);
        return $storage;
    }

    /**
     * Menambah nilai informasi posisi yang berada pada key array.
     *
     * @param $elements array
     *   Merupakan "Array Elements Full" atau "Array Elements Starttag",
     *   lihat pada Definisi FAQ.
     * @param $add int
     *   Angka yang akan ditambah pada key dari parameter $elements.
     */
    protected static function addPosition(&$elements, $add = 0 )
    {
        if ($add === 0) {
            return;
        }
        $positions = array_keys($elements);
        foreach ($positions as &$position) {
            $position += $add;
        }
        $elements = array_combine($positions, $elements);
    }

    /**
     * Mengubah starttag menjadi full element.
     *
     * Mengubah element yang awalnya hanya start tag menjadi
     * lebih lengkap dengan content dan end tag. Untuk void element,
     * maka tidak akan ada perubahan.
     *
     * @param $starttags array
     *   Merupakan "Array Elements Starttag",
     *   lihat pada Definisi FAQ.
     * @param $html string
     *   String dengan format html.
     */
    protected static function constructElements(&$starttags, $html)
    {
        // echo 'var_dump($starttag): '; var_dump($starttag);
        // return;
        // $mask = '/^<(?P<tag>\w+)\s*[^>]*>$/';
        foreach ($starttags as $starttag_lt_position => &$starttag) {
            // echo 'var_dump($starttag_lt_position): '; var_dump($starttag_lt_position);
            // echo 'BEFORE var_dump($starttag): '; var_dump($starttag);
            // echo 'BEFORE var_dump($starttag_lt_position): '; var_dump($starttag_lt_position);
            self::constructElement($starttag_lt_position, $starttag, $html);
            // echo 'AFTER var_dump($starttag): '; var_dump($starttag);
        }
    }

    /**
     * Mengubah starttag menjadi full element.
     *
     * @param $starttag_lt_position int
     *   Jarak pointer ke awal string $html.
     * @param $starttag string
     *   Element starttag.
     * @param $html string
     *   String dengan format html.
     */
    protected static function constructElement($starttag_lt_position, &$starttag, $html)
    {
        // echo 'var_dump($starttag_lt_position): '; var_dump($starttag_lt_position);
        // echo 'var_dump($starttag): '; var_dump($starttag);
        // Validate.
        $mask = '/^<(?P<tag>\w+)\s*[^>]*>$/';
        if (preg_match($mask, $starttag, $matches)) {
            $_starttag = '<' . $matches['tag'];
            $_starttag_length = strlen($_starttag);
            $_endtag = '</' . $matches['tag'] . '>';
            $_endtag_length = strlen($_endtag);
            $offset_starttag = $offset_endtag = $starttag_lt_position + $_starttag_length;
            $endtag_rt_position = false;
            do {
                $distance_starttag = stripos($html, $_starttag, $offset_starttag);
                $distance_endtag = stripos($html, $_endtag, $offset_endtag);
                // Jika endtag tidak ditemukan, maka berarti element ini
                // dianggap single tag.
                if ($distance_endtag === false) {
                    break;
                }
                // JIka jarak ke starttag lebih kecil, berarti benar ada element
                // nested dengan tag sama.
                $nested_exists = $distance_starttag !== false && ($distance_starttag < $distance_endtag);
                if ($nested_exists) {
                    // Perbaiki jarak offset.
                    $offset_starttag = $distance_starttag + $_starttag_length;
                    $offset_endtag = $distance_endtag + $_endtag_length;
                }
                else {
                    $endtag_rt_position = $distance_endtag + $_endtag_length;
                    break;
                }
            } while ($nested_exists);

            if ($endtag_rt_position !== false) {
                $starttag = substr($html, $starttag_lt_position, $endtag_rt_position - $starttag_lt_position);
            }
        }
        return $starttag;
    }

    /**
     * Mengubah full element menjadi starttag.
     *
     * @param $elements array
     *   Merupakan "Array Elements Full",
     *   lihat pada Definisi FAQ.
     * @param $html string
     *   String dengan format html.
     */
    protected static function destructElements(&$elements, $html)
    {
        foreach ($elements as $starttag_lt_position => &$element) {
            self::destructElement($element);
        }
    }

    /**
     * Mengubah full element menjadi starttag.
     *
     * @param $element string
     *   Element full terdiri dari starttag, contents, dan endtag - kecuali
     *   void element.
     */
    protected static function destructElement(&$element)
    {
        list($starttag, $contents, $endtag) = self::parseElement($element);
        $element = $starttag;
    }

    /**
     * Memecah conditions dan dapatkan informasi attribute - attribute
     * yang ada dalam info conditions tersebut.
     */
    protected static function parseConditions($conditions)
    {
        $conditions = (strpos($conditions, ' OR ') !== false) ? explode(' OR ', $conditions) : array($conditions);
        $storage = array();
        foreach ($conditions as $key => $value) {
            if (strpos($value, ' AND ') !== false) {
                $value = explode(' AND ', $value);
                $storage = array_merge($storage, $value);
            }
            else {
             $storage = array_merge($storage, (array) $value);
            }
        }
        $operators = array(
            '=', 'equals', 'is',
            '!=', 'is not',
            '<', 'is less than',
            '>', 'is greater than',
            '<=', 'is less than or equals',
            '>=', 'is greater than or equals',
            '|=', 'contains prefix',
            '~=', 'contains word', 'contains any word',
            '~~=', 'contains all word',
            '!*=', 'does not contain',
            '*=', 'contains',
            '!^=', 'does not start with',
            '^=', 'starts with',
            '!$=', 'does not end with',
            '$=', 'ends with',
        );
        $operators_regex = array();
        foreach ($operators as $value) {
            $operators_regex[] = preg_quote($value, '/');
        }
        $operators_regex = implode('|', $operators_regex);
        $mask = '/^(.+)\s+('.$operators_regex.')\s+(.+)$/i';
        isset(self::$mask) or self::$mask = $mask;
        $fields = array();
        foreach($storage as $condition) {
            if (preg_match($mask, trim($condition), $capture)) {
                $fields[] = $capture[1];
            }
        }
        return $fields;
    }

    /**
     * Validasi seluruh attributes berdasarkan conditions.
     */
    protected static function _validateAttributeConditions($row = array(), $conditions = null)
    {
        if (!empty($row)) {
            if (!empty($conditions)) {
                $conditions = (strpos($conditions, ' OR ') !== false) ? explode(' OR ', $conditions) : array($conditions);
                $or = '';
                foreach ($conditions as $key => $value) {
                    if (strpos($value, ' AND ') !== false) {
                        $value = explode(' AND ', $value);
                        $and   = '';
                        foreach ($value as $k => $v) {
                            $and .= $a = self::_validateAttributeCondition($row, $v);
                        }
                        $or .= (strpos($and, '0') !== false) ? '0' : '1';
                    }
                    else {
                        $or .= $a = self::_validateAttributeCondition($row, $value);
                    }
                }
                return (strpos($or, '1') !== false) ? true : false;
            }
            return true;
        }
        return false;
    }

    /**
     * Validasi satu attributes per satu condition.
     */
    protected static function _validateAttributeCondition($row, $condition)
    {
        if (preg_match(self::$mask, trim($condition), $capture)) {
            $field = $capture[1];
            $op    = $capture[2];
            $value = $capture[3];
            if (preg_match('/^([\'\"]{1})(.*)([\'\"]{1})$/i', $value, $capture)) {
                if ($capture[1] == $capture[3]) {
                    $value = $capture[2];
                    $value = stripslashes($value);
                }
            }
            if (array_key_exists($field, $row)) {
                // Prepare.
                if ($op == '~=' || $op == 'contains word' || $op == 'contains any word' || $op == '~~=' || $op == 'contains all word') {
                    $words = preg_split('/\s/', $row[$field]);
                    $values = preg_split('/\s/', $value);
                }
                // Run logic.
                if (($op == '=' || $op == 'equals' || $op == 'is') && $row[$field] == $value) {
                    return '1';
                }
                elseif (($op == '!=' || $op == 'is not') && $row[$field] != $value) {
                    return '1';
                }
                elseif (($op == '<' || $op == 'is less than' ) && $row[$field] < $value) {
                    return '1';
                }
                elseif (($op == '>' || $op == 'is greater than') && $row[$field] > $value) {
                    return '1';
                }
                elseif (($op == '<=' || $op == 'is less than or equals' ) && $row[$field] <= $value) {
                    return '1';
                }
                elseif (($op == '>=' || $op == 'is greater than or equals') && $row[$field] >= $value) {
                    return '1';
                }
                elseif (($op == '|=' || $op == 'contains prefix') && preg_match('/(?:^' . preg_quote($value, '/') . '$|^' . preg_quote($value, '/') . '\-\w+)/', $row[$field])) {
                    return '1';
                }
                elseif (($op == '~=' || $op == 'contains word' || $op == 'contains any word') && count(array_intersect($words, $values)) !== 0) {
                    return '1';
                }
                elseif (($op == '~~=' || $op == 'contains all word') && count(array_intersect($words, $values)) == count($values)) {
                    return '1';
                }
                elseif (($op == '!*=' || $op == 'does not contain') && !preg_match('/'.preg_quote($value, '/').'/i', $row[$field])) {
                    return '1';
                }
                elseif (($op == '*=' || $op == 'contains') && preg_match('/'.preg_quote($value, '/').'/i', $row[$field])) {
                    return '1';
                }
                elseif (($op == '!^=' || $op == 'does not start with') && !preg_match('/^'.preg_quote($value, '/').'/i', $row[$field])) {
                    return '1';
                }
                elseif (($op == '^=' || $op == 'starts with') && preg_match('/^'.preg_quote($value, '/').'/i', $row[$field])) {
                    return '1';
                }
                elseif (($op == '!$=' || $op == 'does not end with') && !preg_match('/'.preg_quote($value, '/').'$/i', $row[$field])) {
                    return '1';
                }
                elseif (($op == '$=' || $op == 'ends with') && preg_match('/'.preg_quote($value, '/').'$/i', $row[$field])) {
                    return '1';
                }
                else {
                    return '0';
                }
            }
            // Jika attribute tidak ada, maka harus return false.
            else {
                return '0';
            }
        }
        return '1';
    }

    /**
     * Filtering tambahan untuk attribute berdasarkan class.
     */
    protected static function _getElementByClass($value, $start_tag)
    {
        $conditions = $value;
        $conditions = (strpos($conditions, ' OR ') !== false) ? explode(' OR ', $conditions) : array($conditions);
        $attributes = self::extractAttributes($start_tag);
        $classes = preg_split('/\s/', $attributes['class']);
        $or = '';
        foreach ($conditions as $key => $value) {
            if (strpos($value, ' AND ') !== false) {
                $value = explode(' AND ', $value);
                $and   = '';
                foreach ($value as $k => $v) {
                    $and .= in_array($v, $classes) ? '1' : '0';
                }
                $or .= (strpos($and, '0') !== false) ? '0' : '1';
            }
            else {
                $or .= in_array($value, $classes) ? '1' : '0';
            }
        }
        return (strpos($or, '1') !== false) ? true : false;
    }

    /**
     * Filtering tambahan untuk attribute berdasarkan id.
     */
    protected static function _getElementById($value, $start_tag)
    {
        $attributes = self::extractAttributes($start_tag);
        if ($attributes['id'] === $value) {
            return array(
                'break' => true,
            );
        }
        return false;
    }
}
