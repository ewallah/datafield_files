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
 * @package    datafield
 * @subpackage files
 * @copyright  2013 Renaat Debleu (www.eWallah.net)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class data_field_files extends data_field_base {

    public $type = 'files';

    /**
     * This field just sets up a default field object
     *
     * @return bool
     */
    public function define_default_field() {
        global $OUTPUT;
        if (empty($this->data->id)) {
            echo $OUTPUT->notification(get_string('missingdata', 'data'));
        }
        $this->field = new stdClass();
        $this->field->id = 0;
        $this->field->dataid = $this->data->id;
        $this->field->type   = $this->type;
        $this->field->param1 = '';
        $this->field->param2 = '';
        $this->field->param3 = 0; // Max bytes.
        $this->field->param4 = -1;  // Unlimited.
        $this->field->name = '';
        $this->field->description = '';

        return true;
    }


    /**
     * Print the relevant form element in the ADD template for this field
     *
     * @param int $recordid
     * @return string
     */
    public function display_add_field($recordid = 0, $formdata = null) {
        global $DB, $PAGE;
        $context = $PAGE->context;
        $itemid = null;

        if ($recordid > 0) {
            // Editing an existing database entry.
            if ($content = $DB->get_record('data_content', ['fieldid' => $this->field->id, 'recordid' => $recordid])) {
                file_prepare_draft_area($itemid, $context->id, 'mod_data', 'content', $content->id);
            }
        } else {
            $itemid = file_get_unused_draft_itemid();
        }

        $html  = html_writer::start_tag('div', ['title' => s($this->field->description)]);
        $html .= html_writer::start_tag('fieldset');
        $html .= html_writer::start_tag('legend');
        $html .= html_writer::tag('span', $this->field->name, ['class' => 'accesshide']);
        $html .= html_writer::end_tag('legend');

        $options = new stdClass();
        $options->maxbytes = $this->field->param3;
        if ($this->field->param4 == 0) {
            $this->field->param4 = -1;
        }
        $options->maxfiles = $this->field->param4;
        $options->itemid = $itemid;
        $options->accepted_types = '*';
        $options->return_types = FILE_INTERNAL;
        $options->context = $PAGE->context;

        $fm = new form_filemanager($options);
        $output = $PAGE->get_renderer('core', 'files');
        $html .= $output->render($fm);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'field_' . $this->field->id . '_files',
            'value' => $itemid]);
        $html .= html_writer::end_tag('fieldset');
        $html .= html_writer::end_tag('div');

        return $html;
    }

    /**
     * Prints the respective type icon
     * @return string
     */
    public function image() {
        global $OUTPUT;
        $params = ['d' => $this->data->id, 'fid' => $this->field->id, 'mode' => 'display', 'sesskey' => sesskey()];
        $link = new moodle_url('/mod/data/field.php', $params);
        $str  = html_writer::start_tag('a', ['href' => $link->out()]);
        $str .= html_writer::empty_tag('img', [
                  'src' => $OUTPUT->image_url('field/file', 'data'),
                  'height' => $this->iconheight,
                  'width' => $this->iconwidth,
                  'alt' => $this->type,
                  'title' => $this->type]);
        $str .= html_writer::end_tag('a');
        return $str;
    }

    public function display_search_field($value = '') {
        $html  = html_writer::tag('label',
                                  $this->field->name,
                                  ['class' => 'accesshide', 'for' => 'fs_' . $this->field->id]);
        $html .= html_writer::empty_tag('input',
                                  ['type' => 'text', 'size' => 16, 'id' => 'fs_'.$this->field->id,
                                        'name' => 'f_'.$this->field->id, 'value' => $value]);
        return $html;
    }

    public function generate_sql($tablealias, $value) {
        global $DB;
        static $i = 0;
        $i++;
        $name = "df_files_$i";
        return [" ({$tablealias}.fieldid = {$this->field->id} AND ".
                       $DB->sql_like("{$tablealias}.content", ":$name", false).") ",
                       [$name => "%$value%"]];
    }

    public function parse_search_field() {
        return optional_param('f_'.$this->field->id, '', PARAM_NOTAGS);
    }

    /**
     * Display the content of the field in browse mode
     * @param int $recordid
     * @param object $template
     * @return bool|string
     */
    public function display_browse_field($recordid, $template) {
        global  $DB, $OUTPUT;

        if (!$content = $DB->get_record('data_content', ['fieldid' => $this->field->id, 'recordid' => $recordid])) {
            return '';
        }
        if (empty($content->content)) {
            return '';
        } else {
            $fs = get_file_storage();
            $files = $fs->get_area_files($this->context->id, 'mod_data', 'content', $content->id);
            $result = '';
            foreach ($files as $file) {
                if ($file->is_directory()) {
                    continue;
                }
                $filename = $file->get_filename();
                $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                        $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $filename, false);
                if (file_extension_in_typegroup($filename, 'web_image')) {
                    $image = html_writer::empty_tag('img',
                        ['src' => $url->out(false, ['preview' => 'thumb', 'oid' => $file->get_timemodified()]),
                              'alt' => $filename, 'title' => $filename]);
                } else {
                     $image = $OUTPUT->pix_icon(file_file_icon($file, 80), $filename, 'moodle');
                }
                $result .= html_writer::tag('a', $image, ['href' => $url, 'style' => 'margin-right:7px;']);
            }
            return $result;
        }
    }


    /**
     * Update the content of one data field in the data_content table
     * @param int $recordid
     * @param mixed $value
     * @param string $name
     * @return bool
     */
    public function update_content($recordid, $value, $name='') {
        global $DB, $USER;
        $fs = get_file_storage();

        if (!$content = $DB->get_record('data_content', ['fieldid' => $this->field->id, 'recordid' => $recordid])) {

            // Quickly make one now!
            $content = new stdClass();
            $content->fieldid  = $this->field->id;
            $content->recordid = $recordid;
            $id = $DB->insert_record('data_content', $content);
            $content = $DB->get_record('data_content', ['id' => $id]);
        }

        // Delete existing files.
        $fs->delete_area_files($this->context->id, 'mod_data', 'content', $content->id);

        $usercontext = context_user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $value, 'timecreated DESC');

        if (count($files) > 1 ) {
            $content->content = '';
            $vals = [];
            foreach ($files as $draftfile) {
                if (!$draftfile->is_directory()) {
                    $filerecord = [
                        'contextid' => $this->context->id,
                        'component' => 'mod_data',
                        'filearea' => 'content',
                        'itemid' => $content->id,
                        'filepath' => '/',
                        'filename' => $draftfile->get_filename(),
                    ];

                    $vals[] = $filerecord['filename'];
                    $fs->create_file_from_storedfile($filerecord, $draftfile);

                }
            }
            $content->content = implode('##', $vals);
            $DB->update_record('data_content', $content);
        }
    }

    /**
     * Return the record's text value
     *
     * @param string $record
     * @return string
     */
    public function export_text_value($record) {
        if ($this->text_export_supported()) {
            $vals = explode('##', $record->content);
            return implode(',', $vals);
        }
    }

    /**
     * @param string $relativepath
     * @return bool true
     */
    public function file_ok($relativepath) {
        return true;
    }
}
