<?php

namespace format_visualsections\model;

defined('MOODLE_INTERNAL') || die;

class topic extends base_model {

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $name;

    /**
     * @var int
     */
    public $number;

    /**
     * @var int
     */
    public $progress;

    /**
     * @var string json
     */
    public $subtopicsjson;

    /**
     * @var string
     */
    public $cssclass;

    /**
     * @var string
     */
    public $strokecolor;

    /**
     * @var string
     */
    public $link;

    /**
     * @var string
     */
    public $tooltip;

    /**
     * @var bool
     */
    public $locked;

    /**
     * Topic constructor.
     * @param int $progress
     * @param string $subtopicsjson - json data
     * @param string $cssclass
     */
    public function __construct(
                                int     $id,
                                string  $name,
                                int     $number,
                                int     $progress,
                                string  $subtopicsjson,
                                string  $cssclass,
                                string  $strokecolor,
                                ?string $link = null,
                                ?string $tooltip = null,
                                ?bool   $locked = false) {
        $this->set_props_construct_args(func_get_args());
    }

    /**
     * This is here for IDE completion.
     * @param array|object $data
     * @return subsectionß
     * @throws \coding_exception
     */
    public static function from_data($data): topic {
        return parent::do_make_from_data($data);
    }
}