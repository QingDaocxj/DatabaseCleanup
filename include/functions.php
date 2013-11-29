<?php
# Copyright 2013 MTU Aero Engines AG
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#   http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

function get_project_expiration_period($p_project_id){
    $t_expiration_period = plugin_config_get( 'default_expiration_period' );
    $t_project_expiration_period = plugin_config_get( 'project_expiration_period', 0, false, null, $p_project_id);
    if ($t_project_expiration_period != "0") {
        // replace global expiration
        $t_expiration_period = $t_project_expiration_period;
    }
    return $t_expiration_period;
}

// create and return the list of issues matching the configured rules for deletion
function create_bug_list(){
    $t_issues_list = array();

    $t_default_expiration_period = plugin_config_get( 'default_expiration_period' );
    $t_reference_date_field = plugin_config_get('reference_date');
    if ($t_default_expiration_period == "0"){
        // Disabled, return an empty list
        return $t_issues_list;
    }

    $t_minimum_status = plugin_config_get('minimum_status');
    $t_desired_statuses = array();
    $t_available_statuses = MantisEnum::getValues( config_get( 'status_enum_string' ) );
    foreach( $t_available_statuses as $t_this_available_status ) {
        if( $t_this_available_status >= $t_minimum_status ) {
            $t_desired_statuses[] = $t_this_available_status;
        }
    }

    # foreach project
    $t_projects = project_get_all_rows();
    foreach ( $t_projects as $t_project_id => $t_project_data ) {
        $t_expiration_date = strtotime("- ". get_project_expiration_period($t_project_id));
        $t_selected_issues = do_query($t_project_id, $t_desired_statuses);
        foreach ($t_selected_issues as $t_issue) {
            if ($t_issue->$t_reference_date_field < $t_expiration_date ) {
                $t_issue->expiration_date = $t_expiration_date;
                $t_issues_list[] = $t_issue;
            }
        }
    }
    return $t_issues_list;

}


function do_query( $p_project_id, $p_desired_statuses){
    # create filter
    $t_filter = filter_get_default();
    $t_filter[FILTER_PROPERTY_STATUS_ID] = $p_desired_statuses;
    $t_filter[FILTER_PROPERTY_PROJECT_ID] = $p_project_id;
    $t_filter['_view_type'] = 'advanced';

    # Get bug rows according to the current filter
    $t_page_number = 1;
    $t_per_page = -1;
    $t_bug_count = null;
    $t_page_count = null;
    $t_filter_result = filter_get_bug_rows( $t_page_number, $t_per_page, 
        $t_page_count, $t_bug_count, $t_filter);

    if( $t_filter_result === false ) {
        echo "<p>FILTER FAILED!<p>";
        $t_filter_result = array();
    }

    return $t_filter_result;
}


function create_csv($p_issues_list){
    $t_reference_date_field = plugin_config_get('reference_date');
    $t_deletion_time = new DateTime();

    echo '<pre>';
    echo 'project,issue,status,summary,"deleted on",age,"expiration period"'. "\r\n";
    foreach ($p_issues_list as $t_issue) {
        $t_reference_date = DateTime::createFromFormat("U", $t_issue->$t_reference_date_field);
        $t_age = $t_reference_date->diff( $t_deletion_time );
        $t_expiration_period = get_project_expiration_period($t_issue->project_id);
        echo project_get_name($t_issue->project_id) . ','
            . $t_issue->id . ','
            . get_enum_element( 'status', $t_issue->status, NO_USER, $t_issue->project_id ) . ','
            . '"' . $t_issue->summary . '",'
            . $t_deletion_time->format('Y-m-d') . ','
            . $t_age->format('"%d days"'). ','
            . '"' . $t_expiration_period . '"'
            . "\r\n";
    }
    echo '</pre>';
}