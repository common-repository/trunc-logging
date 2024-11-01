<?php


/* Starting - only continue if from WP */
if(!function_exists('add_action') || !function_exists('wp_die'))
{
    exit(0);
}



class Trunc_Logging_Table extends WP_List_Table
{
    function column_default( $item, $column_name ) {
        switch( $column_name ) { 
            case 'date':
                $logtime = strtotime($item[$column_name]);
                $timeago = human_time_diff($logtime, time());
                return ($item[ $column_name ] . "<br /><b>$timeago ago</b>");
                break;
            case 'user':
                return htmlspecialchars(trim($item[ $column_name ], ":"));
                break;
            case 'ip_address':
                $item[ $column_name ] = trim($item[ $column_name ], ":");
                if(strpos($item[ $column_name ], "/") !== FALSE)
                {
                    $realip = htmlspecialchars(explode("/", $item[ $column_name ])[0]);
                }
                else
                {
                    $realip = htmlspecialchars($item[ $column_name ]);
                }

                return '<a href="' . add_query_arg("s", $realip, menu_page_url( 'trunc_logging_settings', false )) . '">' . $realip . '</a>';
                break;
            case 'log':
                return htmlspecialchars($item[ $column_name ]);
            default:
                if ( isset( $item->$column_name ) ) {
                    $return = $item->$column_name;
                    return($return);
                }
                break;
        }
    }

    function get_columns(){
        $columns = array(
            'date' => 'Date',
            'user'    => 'User',
            'ip_address'    => 'IP Address',
            'log'      => 'Log'
        );
        return $columns;
    }

    function get_sortable_columns()
    {
        $sortable_columns = array(
            'date' => array('date',false),
            'user' => array('user',false),
        );
        return $sortable_columns;
    }

    function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $per_page = 200;
        $current_page = $this->get_pagenum();

        $logentries = 0;
        $log_results = array();
        $myfp = @fopen(TRUNCLOGGING_FILE, "r");
        if($myfp)
        {
            $logsize = filesize(TRUNCLOGGING_FILE);
            if($logsize > 101000)
            {
                fseek($myfp, -100000, SEEK_END);
                fgets($myfp, 8192);
            }

            while (($buffer = fgets($myfp, 8192)) !== false)
            {
                if(strpos($buffer,"WPLogging:") === FALSE)
                {
                    continue;
                }
                $logentries++;

                $log_pieces = explode(" ", $buffer, 8);

                $log_entry = array('date' => $log_pieces[0]. ' '. $log_pieces[1], 'severity' => $log_pieces[4], 'ip_address' => $log_pieces[5], 'user' => $log_pieces[6], 'log' => $log_pieces[7]);
                $log_results[] = $log_entry;
            }
            fclose($myfp);
        }

        $log_results = array_reverse($log_results);
        $total_items = $logentries;

        if($logentries > $per_page)
        {
            $this->set_pagination_args(array( 'total_items' => $total_items, 'per_page' => $per_page));
            $to_print_data = array_slice($log_results, (($current_page-1) * $per_page), $per_page);
            $this->items = $to_print_data;
        }
        else
        {
            $this->items = $log_results;
        }
    }


}


