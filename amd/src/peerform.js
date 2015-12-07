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
 * Peerform Javascript
 *
 * @package    mod_peerform
 * @copyright  2015 Owen Barritt, Wine & Spirit Education Trust
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 define(['jquery'], function($) {
    return {
        addSubsToggleListener: function(link, content, img) {
            $('#' + link).on('click', function(event){
                event.preventDefault();
                $('#' + content).toggle();

                if($('#' + content).css('display') == 'none') {
                    $('#' + img).attr('src', M.util.image_url('t/collapsed', 'core'));
                } else {
                    $('#' + img).attr('src', M.util.image_url('t/expanded', 'core'));
                }
            });

            $('#' + link).find('a').on('click', function(event){
                event.stopPropagation();
            });
        }
    };
 });