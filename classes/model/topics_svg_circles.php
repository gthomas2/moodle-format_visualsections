<?php

namespace format_visualsections\model;

defined('MOODLE_INTERNAL') || die;

class topics_svg_circles extends base_model {

    /**
     * @var imageurl[]
     */
    public $imageurls;

    /**
     * @var array
     */
    public $topicgroups;

    /**
     * @var bool
     */
    public $shownavcontrols;

    /**
     * Topcis svg circles constructor.
     * @param array $imageurls
     * @param array $topicgroups
     * @throws \ReflectionException
     */
    public function __construct(array $imageurls, array $topicgroups, ?bool $shownavcontrols = true) {
        $this->set_props_construct_args(func_get_args());
    }

    /**
     * This is here for IDE completion.
     * @param array|object $data
     * @return subsectionß
     * @throws \coding_exception
     */
    public static function from_data($data): topics_svg_circles {
        return parent::do_make_from_data($data);
    }
}