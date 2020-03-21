<?php

namespace format_visualsections\model;

defined('MOODLE_INTERNAL') || die;

use format_visualsections\trait_exportable;

abstract class base_model implements \renderable, \templatable {

    use trait_exportable;

    /**
     * Get constructor args hashed by arg name.
     * @param array $args
     * @return array
     * @throws \ReflectionException
     */
    protected function get_hashed_construct_args(array $args): array {
        $r = new \ReflectionMethod(get_class($this), '__construct');
        $params = $r->getParameters();

        $argshashed = [];
        $a = 0;
        foreach ($params as $param) {
            $argshashed[$param->getName()] = $args[$a];
            $a++;
            if ($a >= count($args)) {
                break;
            }
        }
        return $argshashed;
    }

    /**
     * Set class properties by non-hashed array of args in order of position in constructor.
     * @param array $args
     * @throws \ReflectionException
     */
    protected function set_props_construct_args(array $args) {
        $args = $this->get_hashed_construct_args($args);
        foreach ($args as $key => $val) {
            $this->$key = $val;
        }
    }

    /**
     * All models should implement this method.
     * Populate this models properties by hashed data array or object.
     * @param object|array $data
     * @return mixed
     */
    public abstract static function from_data($data);

    /**
     * Make model from data.
     * @param object|array $data
     * @return base_model;
     */
    protected static function do_make_from_data($data): base_model {
        if (!is_object($data) && !is_array($data)) {
            throw new \coding_exception('$data must be an object or an array');
        }
        $data = (object) $data;
        $classname = get_called_class();
        $r = new \ReflectionMethod($classname, '__construct');
        $params = $r->getParameters();
        $constructargs = [];
        foreach ($params as $param) {
            $paramname = $param->getName();
            $incval = false;
            if (!$param->isOptional()) {
                if (!isset($data->$paramname)) {
                    throw new \coding_exception('$data is missing required param ' . $paramname);
                }
                $incval = true;
                $val = $data->$paramname;
            } else {
                $incval = true;
                $val = isset($data->$paramname) ? $data->$paramname : $param->getDefaultValue();
            }
            if ($incval) {
                $paramtype = $param->hasType() ? $param->getType()->getName() : '';
                if ($paramtype === 'int') {
                    if (is_number($val) && strval(intval($val)) === strval($val)) {
                        $val = intval($val);
                    } else if ($val === "") {
                        $val = null;
                    }
                } else if ($paramtype === 'null') {
                    if ($val === "") {
                        $val = null;
                    }
                }
                $constructargs[] = $val;
            }
        }

        return new $classname(...$constructargs);
    }
}