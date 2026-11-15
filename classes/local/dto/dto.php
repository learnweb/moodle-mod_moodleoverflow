<?php
// This file is part of a plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_moodleoverflow\local\dto;

use core\param;
use core\router\schema\objects\array_of_things;
use core\router\schema\objects\scalar_type;
use core\router\schema\objects\schema_object;
use ReflectionClass;

/**
 * Shared by the data transfer objects. A DTO declares its fields once, as typed readonly properties of its
 * constructor; the array for the page and the schema for the routes are derived from them.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait dto {
    /**
     * The fields as an array, for a REST response or the props of a React page.
     *
     * @return array
     */
    public function to_array(): array {
        return get_object_vars($this);
    }

    /**
     * The schema of the fields for the routes: one value per constructor parameter, required unless it is nullable.
     * Strings are not checked further (RAW), arrays may hold anything.
     *
     * @return schema_object
     */
    public static function schema(): schema_object {
        $content = [];
        foreach ((new ReflectionClass(static::class))->getConstructor()->getParameters() as $parameter) {
            $type = $parameter->getType();
            $content[$parameter->getName()] = match ($type->getName()) {
                'array' => new array_of_things(),
                'int' => new scalar_type(param::INT, required: !$type->allowsNull()),
                'float' => new scalar_type(param::FLOAT, required: !$type->allowsNull()),
                'bool' => new scalar_type(param::BOOL, required: !$type->allowsNull()),
                default => new scalar_type(param::RAW, required: !$type->allowsNull()),
            };
        }
        return new schema_object(content: $content);
    }
}
