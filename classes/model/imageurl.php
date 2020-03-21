<?php

namespace format_visualsections\model;

defined('MOODLE_INTERNAL') || die;

class imageurl extends base_model {
    /**
     * @var string
     */
    public $code;

    /**
     * @var string
     */
    public $url;

    /**
     * Imageurl constructor.
     * @param string $code
     * @param string $url
     */
    public function __construct(string $code, string $url) {
        $this->set_props_construct_args(func_get_args());
    }

    /**
     * This is here for IDE completion.
     * @param array|object $data
     * @return subsectionß
     * @throws \coding_exception
     */
    public static function from_data($data): imageurl {
        return parent::do_make_from_data($data);
    }
}