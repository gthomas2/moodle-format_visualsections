<?php

namespace format_visualsections\model;

defined('MOODLE_INTERNAL') || die;

class subsectiontype extends base_model {

    /**
     * @var string
     */
    public $code;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $image;

    /**
     * @var int
     */
    public $pos;

    /**
     * Subsectiontype constructor.
     * @param string $code
     * @param string $name
     * @param string $image
     * @param int|null $pos
     * @throws \ReflectionException
     */
    public function __construct(string $code, string $name, string $image, ?int $pos) {
        $this->set_props_construct_args(func_get_args());
    }

    /**
     * This is here for IDE completion.
     * @param array|object $data
     * @return subsection
     * @throws \coding_exception
     */
    public static function from_data($data): subsectiontype {
        return parent::do_make_from_data($data);
    }
}