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
 * Strings for component 'enrol_sync', language 'fr'.
 *
 * @package    enrol_sync
 * @copyright  2010 onwards Valery Fremaux  {@link http://www.mylearningfactory.com}
 * @author  2010 onwards Valery Fremaux  {@link http://www.mylearningfactory.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Privacy.
$string['privacy:metadata'] = "La méthode d\'inscription synchronidée ne stoke pas de données relatives aux utilisateurs.";

$string['configcanhideshowinstances'] = 'Activer/désactiver les instances';
$string['configcanhideshowinstances_desc'] = 'Si cette option est cochée, alors les personnes pouvant gérer les méthodes
d\'inscription pourront activer et désactiver les inscriptions synchronisées. Une instance désactivée recevra néanmoins toujours
les changements poussés par la synchronisation.';
$string['pluginname'] = 'Inscriptions automatisées';
$string['status'] = 'Autoriser les insciptions synchronisées';
$string['status_desc'] = 'Allow temporary synced access by default.';
$string['status_help'] = 'This setting determines whether a user can access the course when owning a synced enrolment instance.';
$string['status_link'] = 'enrol/synced';
$string['sync:config'] = 'Peut configurer';

<<<<<<< HEAD
$string['pluginname_desc'] = 'Ce plugin n\'est pas interactif et sert à la spécialisation des inscriptions obtenues par synchronisation
automatique par des fichiers CSV, ou par des agents d\'alimentation automatisés.';
<<<<<<< HEAD
=======
=======
$string['pluginname_desc'] = 'Ce plugin n\'est pas interactif et sert à la spécialisation des inscriptions obtenues par
synchronisation automatique par des fichiers CSV, ou par des agents d\'alimentation automatisés.';
>>>>>>> MOODLE_401_STABLE

$string['wsusercannotassign'] = 'L\'utilisateur courant n\'a pas le droit d\'assigner le role ({$a->roleid}) à cet utilisateur
({$a->userid}) dans ce cours ({$a->courseid}).';
$string['syncpluginnotinstalled'] = 'Le plugin d\'inscription "Sync" n\'a pas été installé ou activé ';
>>>>>>> MOODLE_401_STABLE
