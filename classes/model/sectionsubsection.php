<?php

namespace format_visualsections\model;

defined('MOODLE_INTERNAL') || die;

class sectionsubsection extends base_model {
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $subsectionsid;

    /**
     * @var string
     */
    public $class;

    /**
     * @var string
     */
    public $headingid;

    /**
     * @var string
     */
    public $collapseid;

    /**
     * @var string
     */
    public $imageurl;

    /**
     * @var string
     */
    public $sizeicon;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $cardbody;

    /**
     * @var bool
     */
    public $allowmoveup;

    /**
     * @var bool
     */
    public $allowmovedown;

    /**
     * @var string
     */
    public $deleteurl;

    /**
     * @var bool
     */
    public $editmode;

    /**
     * @var bool
     */
    public $show;

    public function __construct(
        string   $id,
        string   $subsectionsid,
        string   $class,
        string   $headingid,
        string   $collapseid,
        string   $imageurl,
        string   $sizeicon,
        string   $title,
        string   $cardbody,
        bool     $allowmoveup,
        bool     $allowmovedown,
        string   $deleteurl,
        ?bool    $editmode = false,
        ?bool    $show = false) {
        $this->set_props_construct_args(func_get_args());
    }

    /**
     * This is here for IDE completion.
     * @param array|object $data
     * @return subsection
     * @throws \coding_exception
     */
    public static function from_data($data): sectionsubsection {
        return parent::do_make_from_data($data);
    }
}