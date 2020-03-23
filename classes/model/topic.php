<?php

namespace format_visualsections\model;

defined('MOODLE_INTERNAL') || die;

class topic extends base_model {

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
     * Topic constructor.
     * @param int $progress
     * @param string $subtopicsjson - json data
     * @param string $cssclass
     */
    public function __construct(int $progress, string $subtopicsjson, string $cssclass, string $strokecolor) {
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