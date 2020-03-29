<?php

namespace format_visualsections\model;

defined('MOODLE_INTERNAL') || die;

class subsection extends base_model {
    /**
     * @var int
     */
    public $parentid;

    /**
     * @var string
     */
    public $typecode;

    /**
     * @var string
     */
    public $size;

    /**
     * @var int
     */
    public $id;


    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $link;

    /**
     * Subsection constructor.
     * @param int $parentid
     * @param string $name
     * @param string $type
     * @param int|null $id
     */
    public function __construct(
        int     $parentid,
        string  $typecode,
        string  $size,
        ?string $name = null,
        ?int    $id = null,
        ?string $link = null) {
        $this->set_props_construct_args(func_get_args());
    }

    /**
     * This is here for IDE completion.
     * @param array|object $data
     * @return subsection
     * @throws \coding_exception
     */
    public static function from_data($data): subsection {
        return parent::do_make_from_data($data);
    }
}