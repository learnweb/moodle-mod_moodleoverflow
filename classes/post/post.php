<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Class for working with posts
 *
 * @package     mod_moodleoverflow
 * @copyright   2023 Tamaro Walter
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_moodleoverflow\post;

// Import namespace from the locallib, needs a check later which namespaces are really needed
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\capabilities;
use mod_moodleoverflow\review;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/lib.php');

/**
 * Class that represents a post.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post {
    /**
     * Wie funktioniert das erstellen, ändern und löschen eines posts? 
     * Was mach die post.php und die classes/post_form.php, wozu sind post.mustache und post_dummy.mustache da? 
     * Welche funktionen zu den posts hat die locallib.php?
     * 
     * => Was sollte in diese Klasse (classes/post.php) integriert werden, was sollte sie können? Was lässt man in der post.php/locallib.php, braucht man später die postform noch?
     * 
     * Funktionen aus der Locallib, die mit posts zu tun haben:
     * - function moodleoverflow_get_post_full($postid) -> holt infos um einen post zu printen
     * - function moodleoverflow_user_can_see_post($moodleoverflow, $discussion, $post, $cm)
     * - function moodleoverflow_get_all_discussion_posts($discussionid, $tracking, $modcontext) -> holt sich die posts einer discussion
     * - function moodleoverflow_print_post(...)
     * - function moodleoverflow_print_posts_nested
     * - function get_attachments($post, $cm)  -> 
     * - function moodleoverflow_add_attachment($post, $forum, $cm) {
     * - function moodleoverflow_add_new_post($post) {
     * - function moodleoverflow_update_post($newpost) {
     * - function moodleoverflow_count_replies($post, $onlyreviewed) -> zählt antworten auf eine frage  
     * - function moodleoverflow_delete_post($post, $deletechildren, $cm, $moodleoverflow) {
     * - function moodleoverflow_add_discussion($discussion, $modulecontext, $userid = null) {
     * 
     * 
     * Vorüberlegung zu classes/post.php: 
     * - Jeder Post im moodleoverflow ist ein Objekt identifizierbar über seine ID
     * - Bei der erstellung einer neuen Diskussion (automatisch mit einem post) oder beim antworten/kommentieren soll ein neues objekt DIESER Klasse erstellt werden
     * => realisierung der funktionen add_new_post, update_post, delete_post
     * 
     * - Die funkionen:
     *   - get_post_full, print_post, 
     *   - add_attachment und get_attachments
     *   sollten auch hier programmiert sein.
     *   Es soll auch möglich sein, den Elternpost, die Diskussion oder das Moodleoverflow als objekt zurückzugeben, damit alle Datenbankaufrufe hier passieren und nicht woanders.
     * 
     * - Diese Funktionen sollten in der Locallib bleiben:
     *   - user_can_see_post, da es von außen nur auf einen existierenden post zugreift
     *   - get_all_discussion_posts, da ein post nicht weitere posts in seiner umgebung kennt (außer seinen Elternpost)
     *   - print_post_nested, auch hier der gleiche grund, außerdem ruft print_post_nested auch nur print_posts_nested und geht zum nächsten Post
     *   - count_replies, da hier eine sammlung von posts abgeprüft wird.
     *   - add_discussion() bleibt in der locallib, da hier nur die construct methode (add_new_post()) aufgerufen wird.
     *
     *
     * 
     * 
     * Wie funktionieren post.php und classes/post_form.php? 
     * 
     * post.php:
     * Bei jeder art von Interaktion (erstellen, ändern, löschen) wird die post.php aufgerufen
     * => Im 1. Schritt wird geprüft, welche Art von Interaktion vorliegt. -> vielliecht auch das in classes/post.php auslagern
     * => Im 2. Schritt wird ein neues post_form Objekt gebaut und dem objekt neue funktionen übergeben
     * 
     * 
     * classes/post_form.php:
     * Bildet nur die form ab, wo man den titel, Inhalt und attachments seines posts eintragen kann
     */
}