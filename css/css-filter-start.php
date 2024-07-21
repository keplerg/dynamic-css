<?php 
/**
 * Dynamic CSS Filter
 *
 * An open source dynamic CSS filter.
 *
 * @author        Kepler Gelotte
 * @copyright     Copyright (c) 2008, Neighbor Webmaster, Inc.
 * @license       http://www.coolphptools.com/dynamic_css/license
 * @link          http://www.coolphptools.com
 * @version       Version 3.0
 */

/* for command line testing
$_SERVER = [
    'HTTP_USER_AGENT' => 'linux',
    'REQUEST_URI' => 'test.css',
    'QUERY_STRING' => '',
];
 */

$debug = false;
$cache = true;
$allow_eol_comments = true;
$compress = true;
$compress_comments = true;
$handle_pngs = true;
// NOTE: I have had no luck using the alpha filter!
$use_alpha_filter = false; // Use the alpha filter for IE5.5 & IE6?
$filter_sizing_method = 'scale'; // valid values are 'crop', 'image', 'scale'
$convert_to_png8 = false; // If set to true creates an 8-bit PNG, otherwise creates a GIF
$matte_color = 'fff'; // matte color or alpha blending on transparent images

$_dyncss_system_boolean_parameters = array('debug', 'cache', 'allow_eol_comments', 'compress', 'compress_comments', 'handle_pngs', 'use_alpha_filter', 'convert_to_png8');
$_dyncss_system_string_parameters = array('filter_sizing_method');
$_dyncss_expression_tokens = array('if','elseif','elif','eval');
$_dyncss_vars = array();

// see if any settings were overridden
if (isset($_SERVER['QUERY_STRING']))
{
    $_dyncss_arr = explode('&', html_entity_decode(ltrim($_SERVER['QUERY_STRING'], '?')));
    foreach ($_dyncss_arr as $_dyncss_arg)
    {
        if (($_dyncss_pos = strpos($_dyncss_arg, '=')) > 0)
        {
            $_dyncss_key = substr($_dyncss_arg, 0, $_dyncss_pos);
            $_dyncss_value = substr($_dyncss_arg, $_dyncss_pos+1);
            if (in_array($_dyncss_key, $_dyncss_system_boolean_parameters))
            {
                if ( strcasecmp($_dyncss_value, 'true') === 0
                    || strcasecmp($_dyncss_value, 'yes') === 0
                    || $_dyncss_value === 1 )
                {
                    $$_dyncss_key = true;
                }
                else
                {
                    $$_dyncss_key = false;
                }
            }
            elseif (in_array($_dyncss_key, $_dyncss_system_string_parameters))
            {
                $$_dyncss_key = $_dyncss_value;
            }
        }
    }
}

function process( $_dyncss_fn, $_dyncss_level, $_dyncss_lines ) {
    global $debug, $cache, $allow_eol_comments, $compress, $compress_comments, $handle_pngs, $matte_color, $_dyncss_expression_tokens, $_dyncss_vars; // , $_dyncss_start, $_dyncss_expires, $_dyncss_output;
    //
    $_dyncss_line_number = 0;
    $_dyncss_if_level = 0;
    $_dyncss_ifs = array();
    $_dyncss_suppress_to_endif = false;
    $_dyncss_prev_line = '';
    $_dyncss_output = '';
    $_dyncss_show_output = true;
    // Can't use foreach since $_dyncss_lines is modified in the loop
    foreach ( $_dyncss_lines as $_dyncss_index => $_dyncss_line )
    {
        $_dyncss_line_number++;

        if ($allow_eol_comments)
        {
            if ($compress_comments)
            {
                // remove end-of-line comments (like this one)
                $_dyncss_line = preg_replace( '#([^"\':]|^)//.*#', '${1}', $_dyncss_line );
            }
            else
            {
                // convert end-of-line comments to regular comments
                $_dyncss_line = preg_replace( '#([^"\':]|^)//\s*([^\r\n]*)\s*#', "\${1}/* \${2} */", $_dyncss_line );
            }
        }

        // see if the newline was escaped - if so, concatenate the line
        $_dyncss_line = rtrim($_dyncss_line, "\r\n");
        if (strlen($_dyncss_line) > 0 && $_dyncss_line[(strlen($_dyncss_line) - 1)] == "\\")
        {
            $_dyncss_prev_line .= substr($_dyncss_line, 0, -1);
            continue;
        }
        $_dyncss_line = $_dyncss_prev_line . $_dyncss_line;
        $_dyncss_prev_line = '';

        $_dyncss_first_token = strtolower( preg_replace( '#([^ \t\n=;]*).*#', '$1', $_dyncss_line ) );
        if ($debug)
        {
            $_dyncss_output .= '/* DEBUG first_token: <'.$_dyncss_first_token.'>';
        }
        $_dyncss_contains_expression = ( in_array( $_dyncss_first_token, $_dyncss_expression_tokens ) );
        $_dyncss_spans_lines = ((strlen($_dyncss_line) > 0 && $_dyncss_line[strlen( $_dyncss_line ) - 1] == '\\')?true:false );
        if ($debug)
        {
            $_dyncss_output .= ' contains expression: <'.(($_dyncss_contains_expression)?'YES':'NO').'> spans lines: <'.(($_dyncss_spans_lines)?'YES':'NO').'> line: '.$_dyncss_line_number." */\n";
        }
        $_dyncss_line = substitute_delimited_vars( $_dyncss_line, $_dyncss_vars, ($_dyncss_contains_expression && ! $_dyncss_spans_lines) );
        $_dyncss_clauses = preg_split( '/([\{\};])/', str_replace('\\;', '<--|-->', $_dyncss_line), -1, PREG_SPLIT_DELIM_CAPTURE );
        $_dyncss_suppress_delimiter = false;

        foreach( $_dyncss_clauses as $_dyncss_clause )
        {
            $_dyncss_clause = str_replace('<--|-->', ';', $_dyncss_clause);
            if ($debug)
            {
                $_dyncss_output .= "/* DEBUG clause: ".$_dyncss_clause." line: ".$_dyncss_line_number." */\n";
            }

if ($debug) {$_dyncss_output .= "/* DEBUG line: {$_dyncss_line_number} Clause=<".$_dyncss_clause."> - should execute? ".(($_dyncss_show_output)?"YES":"NO")." */\n";}
            if (preg_match( '#^\s*expires\s*(.*)#i', $_dyncss_clause, $_dyncss_matches) === 1)
            {
                $_dyncss_suppress_delimiter = true;
                if ( $_dyncss_show_output )
                {
                    $_dyncss_expires = rtrim($_dyncss_matches[1], "\r\n\t ;");
                    $_dyncss_expires = substitute_vars( $_dyncss_expires, $_dyncss_vars );
                    $_dyncss_expires = preg_replace( '#immediate|yesterday#i', '-86400', $_dyncss_expires );
                    $_dyncss_expires = preg_replace( '#now#i', '0', $_dyncss_expires );
                    $_dyncss_expires = preg_replace( '#minute[s]?#i', '*60', $_dyncss_expires );
                    $_dyncss_expires = preg_replace( '#hour[s]?#i', '*3600', $_dyncss_expires );
                    $_dyncss_expires = preg_replace( '#day[s]?#i', '*86400', $_dyncss_expires );
                    $_dyncss_expires = preg_replace( '#week[s]?#i', '*604800', $_dyncss_expires );
                    $_dyncss_expires = preg_replace( '#month[s]?#i', '*2592000', $_dyncss_expires ); // approx.
                    $_dyncss_expires = preg_replace( '#year[s]?#i', '*31536000', $_dyncss_expires );
                    $_dyncss_expires = @eval( 'return (int)'.substitute_vars( $_dyncss_expires, $_dyncss_vars ).';' );

                    if ($debug)
                    {
                        $_dyncss_output .= "/* DEBUG expires = ".$_dyncss_expires." line: ".$_dyncss_line_number." */\n";
                    }
                }
            }
            else if (preg_match( '#^\s*set-header\s*(.*)#i', $_dyncss_clause, $_dyncss_matches) === 1)
            {
                $_dyncss_suppress_delimiter = true;
                if ( $_dyncss_show_output )
                {
                    $_dyncss_header = rtrim($_dyncss_matches[1], "\r\n\t ;");
                    $_dyncss_header = substitute_vars( $_dyncss_header, $_dyncss_vars );
                    header($_dyncss_header);

                    if ($debug)
                    {
                        $_dyncss_output .= "/* DEBUG set-header = ".$_dyncss_header." line: ".$_dyncss_line_number." */\n";
                    }
                }
            }
            else if (preg_match( '#^\s*set\s+\$?([_a-z][a-z0-9_-]*)\s*=?\s*(.*)\s*#i', $_dyncss_clause, $_dyncss_matches) === 1)
            {
                $_dyncss_suppress_delimiter = true;
                if ( $_dyncss_show_output )
                {
                    $_dyncss_key = $_dyncss_matches[1];
                    $_dyncss_value = substitute_vars( strip_quotes( rtrim($_dyncss_matches[2], "\r\n\t ;") ), $_dyncss_vars );
                    if ($debug)
                    {
                        $_dyncss_output .= "/* DEBUG set ".$_dyncss_key." = ".$_dyncss_value." */\n";
                    }
                    $_dyncss_vars[$_dyncss_key] = $_dyncss_value;
                    $$_dyncss_key = $_dyncss_value;
                }
            }
            else if (preg_match( '#^\s*eval\s+\$?([_a-z][a-z0-9_-]*)\s*=?\s*(.*)#i', $_dyncss_clause, $_dyncss_matches) === 1)
            {
                $_dyncss_suppress_delimiter = true;
                if ( $_dyncss_show_output )
                {
                    $_dyncss_key = $_dyncss_matches[1];
                    $_dyncss_value = rtrim($_dyncss_matches[2], "\r\n\t ;");
                    $_dyncss_expr = @eval( 'return '.substitute_vars( $_dyncss_value, $_dyncss_vars, true ).';' );
                    if ($debug)
                    {
                        $_dyncss_output .= "/* DEBUG eval $".$_dyncss_key." = ".$_dyncss_value."  result: (".$_dyncss_expr.") */\n";
                    }
                    $_dyncss_vars[$_dyncss_key] = $_dyncss_expr;
                    $$_dyncss_key = $_dyncss_expr;
                }
            }
            else if (preg_match( '#^\s*eval\s+(.*)#i', $_dyncss_clause, $_dyncss_matches) === 1)
            {
                $_dyncss_suppress_delimiter = true;
                if ( $_dyncss_show_output )
                {
                    $_dyncss_value = rtrim($_dyncss_matches[1], "\r\n\t ;");
                    $_dyncss_expr = @eval( 'return '.substitute_vars( $_dyncss_value, $_dyncss_vars, true ).';' );
                    if ($debug)
                    {
                        $_dyncss_output .= "/* DEBUG eval ".$_dyncss_value."  result: (".$_dyncss_expr.") */\n";
                    }
                }
            }
            else if (preg_match( '#^\s*matte-color:\s+\#([0-9abcdef]{6}|[0-9abcdef]{3})\s*#i', $_dyncss_clause, $_dyncss_matches) === 1)
            {
                $_dyncss_suppress_delimiter = true;
                if ( $_dyncss_show_output )
                {
                    $matte_color = substitute_vars( $_dyncss_matches[1], $_dyncss_vars );
                    if ($debug)
                    {
                        $_dyncss_output .= "/* DEBUG matte-color: ".$matte_color." */\n";
                    }
                }
            }
            else if (preg_match( '#^\s*if\s+(.*)\s*#i', $_dyncss_clause, $_dyncss_matches) === 1)
            {
                $_dyncss_suppress_delimiter = true;
                if ( $_dyncss_show_output )
                {
                    $_dyncss_value = rtrim($_dyncss_matches[1], "\r\n\t ;");
                    $_dyncss_ifs[$_dyncss_if_level] = @eval( 'return (boolean)'.substitute_vars( $_dyncss_value, $_dyncss_vars, true ).';' );
                    $_dyncss_show_output = $_dyncss_ifs[$_dyncss_if_level];
                    if ($debug)
                    {
                        $_dyncss_output .= "/* DEBUG if ".$_dyncss_value."  result: (".(($_dyncss_ifs[$_dyncss_if_level])?'TRUE':'FALSE').") */\n";
                    }
                }
                else
                {
                    $_dyncss_ifs[$_dyncss_if_level] = false;
                }
                $_dyncss_if_level++;
            }
            else if (preg_match( '#^\s*elif\s+(.*)\s*#i', $_dyncss_clause, $_dyncss_matches) === 1
                || preg_match( '#^\s*elseif\s+(.*)\s*#i', $_dyncss_clause, $_dyncss_matches) === 1)
            {
                $_dyncss_suppress_delimiter = true;
                $_dyncss_suppress_to_endif = $_dyncss_suppress_to_endif || $_dyncss_ifs[($_dyncss_if_level - 1)];
                if ( ! $_dyncss_suppress_to_endif )
                {
                    if ( ! $_dyncss_ifs[($_dyncss_if_level - 1)] )
                    {
                        $_dyncss_value = rtrim($_dyncss_matches[1], "\r\n\t ;");
                        $_dyncss_ifs[($_dyncss_if_level - 1)] = @eval( 'return (boolean)'.substitute_vars( $_dyncss_value, $_dyncss_vars, true ).';' );
                        // $_dyncss_show_output = $_dyncss_ifs[($_dyncss_if_level - 1)];
                        $_dyncss_show_output = should_execute( $_dyncss_ifs, $_dyncss_if_level );
                        if ($debug)
                        {
                            $_dyncss_output .= "/* DEBUG elseif ".$_dyncss_value."  result: (".(($_dyncss_ifs[$_dyncss_if_level - 1])?'TRUE':'FALSE').") */\n";
                        }
                    }
                    elseif ($debug)
                    {
                        $_dyncss_output .= "/* DEBUG elseif not called level: ".($_dyncss_if_level - 1)."  if state: (".(($_dyncss_ifs[$_dyncss_if_level - 1])?'TRUE':'FALSE').") */\n";
                    }
                }
                else
                {
                    $_dyncss_show_output = false;
                    if ($debug)
                    {
                        $_dyncss_output .= "/* DEBUG elseif not called level: ".($_dyncss_if_level - 1)."  already satisfied condition (short circuited) */\n";
                    }
                }
            }
            else if (preg_match( '#^\s*else\s*#i', $_dyncss_clause, $_dyncss_matches) === 1)
            {
                $_dyncss_suppress_delimiter = true;
                $_dyncss_ifs[($_dyncss_if_level - 1)] = ! $_dyncss_ifs[($_dyncss_if_level - 1)];
                $_dyncss_show_output = should_execute( $_dyncss_ifs, $_dyncss_if_level );
            }
            else if (preg_match( '#^\s*endif\s*#i', $_dyncss_clause, $_dyncss_matches) === 1)
            {
                $_dyncss_suppress_delimiter = true;
                $_dyncss_suppress_to_endif = false;
                $_dyncss_if_level--;
                if ($_dyncss_if_level < 0)
                {
                    $_dyncss_output .= "/* ERROR unmatched endif at line ".$_dyncss_line_number." */";
                }
                $_dyncss_show_output = should_execute( $_dyncss_ifs, $_dyncss_if_level );
            }
            else if (preg_match( '#^\s*@include\s*url\(\s*["\']?([^"\'\s]+)["\']?\s*\)\s*#i', $_dyncss_clause, $_dyncss_matches) === 1
                || preg_match( '#^\s*@include\s*["\']?([^"\'\s]+)["\']?\s*#i', $_dyncss_clause, $_dyncss_matches) === 1)
            {
                $_dyncss_suppress_delimiter = true;
                $_dyncss_value = rtrim($_dyncss_matches[1], "\r\n\t ;");
                $_dyncss_fn = substitute_vars( $_dyncss_value, $_dyncss_vars );
                if ($debug)
                {
                    $_dyncss_output .= "/* DEBUG including style file ".$_dyncss_fn." */";
                }
                $_dyncss_contents = file_get_contents( $_dyncss_fn );
                $_dyncss_output .= process( $_dyncss_fn, ($_dyncss_level+1), explode( "\n", $_dyncss_contents ) );
                if ( $cache && ! preg_match( '/http[s]?:\/\/.*/', $_dyncss_fn) )
                {
                    $_dyncss_dependent_files[$_dyncss_fn] = @stat( $_dyncss_fn );
                }
            }
            else
            {
                if ( $_dyncss_show_output )
                {
                    $_dyncss_clause = substitute_vars( $_dyncss_clause, $_dyncss_vars );

                    if ( $handle_pngs && stristr($_dyncss_clause, '.png') )
                    {
                        $_dyncss_clause = handle_pngs( $_dyncss_clause );
                    }
                    if ($_dyncss_clause != ';' || ! $_dyncss_suppress_delimiter)
                    {
                        $_dyncss_output .= trim($_dyncss_clause) . "\n";
                        $_dyncss_suppress_delimiter = false;
                    }
                }
            }
        }
    }

    if ($_dyncss_if_level > 0)
    {
        $_dyncss_output .= "/* ERROR ".$_dyncss_if_level." unterminated if statements */\n";
    }

    return $_dyncss_output;
}

function filter( $_dyncss_input )
{
    global $debug, $cache, $allow_eol_comments, $compress, $compress_comments, $handle_pngs, $matte_color, $_dyncss_expression_tokens, $_dyncss_vars; // , $_dyncss_start, $_dyncss_expires, $_dyncss_output;

    header("Content-type: text/css;");
    header("Cache-Control: must-revalidate;");

    if ( $cache )
    {
        // see if we need a browser specific cache file
        $_dyncss_browser = '';
        if ( $handle_pngs )
        {
            $_dyncss_msie_no_filter = '/msie\s([1-4]|5\.[0-4]).*(win)/i';
            $_dyncss_msie = '/msie\s(5\.[5-9]|6\.).*(win)/i';
            $_dyncss_msie_ok = '/msie\s[7-9].*(win)/i';
            $_dyncss_msie_no_filter = '/msie\s([0-4]\.|5\.[0-4]).*(win)/i';
            $_dyncss_opera = '/opera/i';

            if ( isset($_SERVER['HTTP_USER_AGENT']) )
            {
                if ( preg_match($_dyncss_msie, $_SERVER['HTTP_USER_AGENT'])
                    && ! preg_match($_dyncss_msie_ok, $_SERVER['HTTP_USER_AGENT'])
                    && ! preg_match($_dyncss_opera, $_SERVER['HTTP_USER_AGENT']) )
                {
                    $_dyncss_browser = 'ie6_';
                }
                elseif ( preg_match($_dyncss_msie_no_filter, $_SERVER['HTTP_USER_AGENT'])
                    && ! preg_match($_dyncss_msie_ok, $_SERVER['HTTP_USER_AGENT'])
                    && ! preg_match($_dyncss_opera, $_SERVER['HTTP_USER_AGENT']) )
                {
                    $_dyncss_browser = 'ie5_';
                }
            }
        }

        $cache_dir = getcwd()."/cache/";
        $cache_file = $cache_dir.$_dyncss_browser.str_replace('?', '_', str_replace('&', '_', basename($_SERVER['REQUEST_URI'])));
        $_dyncss_dependency_file = $cache_dir.$_dyncss_browser."dependency_".str_replace('?', '_', str_replace('&', '_', basename($_SERVER['REQUEST_URI'])));
        $_dyncss_css_file = getcwd()."/".$_dyncss_browser.preg_replace('/\?.*/', '', basename($_SERVER['REQUEST_URI']));
        if ( file_exists( $cache_file ) )
        {
            $cache_stat = @stat( $cache_file );
            $_dyncss_use_cache_file = true;

            // first see if the main CSS file is newer than cache
            $_dyncss_css_stat = @stat( $_dyncss_css_file );
            if ( $cache_stat['mtime'] < $_dyncss_css_stat['mtime'] )
            {
                $_dyncss_use_cache_file = false;
            }

            if ( $_dyncss_use_cache_file && file_exists( $_dyncss_dependency_file ) )
            {
                $_dyncss_fp = fopen( $_dyncss_dependency_file, 'r' );
                $_dyncss_contents = fread($_dyncss_fp, filesize($_dyncss_dependency_file));
                fclose($_dyncss_fp);
                if ( $_dyncss_dependent_files = @unserialize( $_dyncss_contents ) )
                {
                    foreach ( $_dyncss_dependent_files as $_dyncss_file => $_dyncss_stat )
                    {
                        $_dyncss_css_stat = @stat( getcwd()."/".$_dyncss_file );
                        if ( ! $_dyncss_css_stat || $_dyncss_css_stat['mtime'] > $_dyncss_stat['mtime'] )
                        {
                            $_dyncss_use_cache_file = false;
                            break;
                        }
                    }
                }
            }

            // is the cache file still Ok?
            if ( $_dyncss_use_cache_file )
            {
                $_dyncss_output = file_get_contents( $cache_file );
                return "/* from cache */\n".$_dyncss_output;
            }
        }
        $_dyncss_dependent_files = array();
    }

    $_dyncss_output = "";

    $_dyncss_expires = 31536000; // default expires in 1 year
    $_dyncss_start = microtime(true);
    // session_start();
    $_dyncss_level = 0;
    if ($debug)
    {
        $_dyncss_output = "/* DEBUG filter start ".date_format(date_create("now"), "Y-m-d H:i:s")." */\n";
    }

    // treat any passed parameters as variables
    if (isset($_SERVER['QUERY_STRING']))
    {
        $_dyncss_arr = explode('&', html_entity_decode(ltrim($_SERVER['QUERY_STRING'], '?')));
        foreach ($_dyncss_arr as $_dyncss_arg)
        {
            if (($_dyncss_pos = strpos($_dyncss_arg, '=')) > 0)
            {
                $_dyncss_key = substr($_dyncss_arg, 0, $_dyncss_pos);
                $_dyncss_value = strip_quotes( substr($_dyncss_arg, $_dyncss_pos+1) );
                $_dyncss_vars[$_dyncss_key] = $_dyncss_value;
                $$_dyncss_key = $_dyncss_value;
            }
        }
    }

    $_dyncss_output .= process( basename($_SERVER['REQUEST_URI']), $_dyncss_level, explode( "\n", $_dyncss_input ) );

    $_dyncss_expDate = gmdate("D, d M Y H:i:s", time() + $_dyncss_expires) . " GMT";
    header("Expires: ".$_dyncss_expDate);
    header("Last-Modified: ".$_dyncss_expDate);
    if ($debug)
    {
        $_dyncss_output .= "/* DEBUG processing time = ".(microtime(true) - $_dyncss_start)." seconds. expires = ".$_dyncss_expDate." */\n";
        $_dyncss_output .= "/* DEBUG filter end ".date_format(date_create("now"), "Y-m-d H:i:s")." */\n";
    }

    if ($compress_comments)
    {
        // remove standard comments
        $_dyncss_output = preg_replace( '#/\*(?!\ DEBUG|\ INFO|\ WARNING|\ ERROR)[^*]*\*+(?:[^/*][^*]*\*+)*/#', '', $_dyncss_output );
        // $_dyncss_output = preg_replace( '#/\*[^*]*\*+([^/*][^*]*\*+)*/#', '', $_dyncss_output );
    }

    if ($compress)
    {
        // remove blank lines
        $_dyncss_output = preg_replace( '#^\s*\n+#', "", $_dyncss_output );
        $_dyncss_output = preg_replace( '#\n+\s*\n+#', "\n", $_dyncss_output );
        // remove extra whitespace
        $_dyncss_output = preg_replace( '#\n?\s*([\{\}:;,])\s*#', "$1", $_dyncss_output );
        // remove extra semicolons
        $_dyncss_output = preg_replace( '#;;*#', ";", $_dyncss_output );
        // remove extra semicolon before close brace
        $_dyncss_output = preg_replace( '#;?(\}+)#', "$1\n", $_dyncss_output );
        // add back newline when @ follows ;
        $_dyncss_output = preg_replace( '#;@([^;]*;)#', ";\n@$1\n", $_dyncss_output );
    }
    else
    {
        // remove duplicate blank lines
        $_dyncss_output = preg_replace( '#\n+\s*\n+#', "\n", $_dyncss_output );
        // remove newline before ;
        $_dyncss_output = preg_replace( '#\n?\s*([;])#', "$1", $_dyncss_output );
        // add back newline when @ follows ;
        $_dyncss_output = preg_replace( '#;@([^;]*;)#', ";\n@$1\n", $_dyncss_output );
        // indent lines containing a single : ending with a ;
        $_dyncss_output = preg_replace( '#\n([^:@\n]+):\s*([^:\n]+;)#', "\n    $1: $2", $_dyncss_output );
    }

    if ( $cache )
    {
        if ( ! file_exists( $cache_dir ) )
        {
            // create cache directory and make it writable
            mkdir( $cache_dir );
            @chmod( $cache_dir, 0777 );
        }
        $_dyncss_rc = @file_put_contents( $cache_file, $_dyncss_output );
        // $_dyncss_output .= "/* file put returned ".$_dyncss_rc." */";

        // save the depencies if there are any
        if ( count( $_dyncss_dependent_files ) > 0 )
        {
            $_dyncss_fp = fopen( $_dyncss_dependency_file, 'w' );
            $_dyncss_contents = @serialize( $_dyncss_dependent_files );
            fwrite($_dyncss_fp, $_dyncss_contents);
            fclose($_dyncss_fp);
        }
    }

    return $_dyncss_output;
}

function should_execute( $_dyncss_ifs, $_dyncss_if_level )
{
    for ($_dyncss_i = 0; $_dyncss_i < $_dyncss_if_level; $_dyncss_i++)
    {
        if ( ! $_dyncss_ifs[$_dyncss_i] )
        {
            return false;
        }
    }

    return true;
}

function substitute_delimited_vars( $_dyncss_line, $_dyncss_vars = array(), $_dyncss_quote_strings = false )
{
    foreach ( $_dyncss_vars as $_dyncss_key => $_dyncss_value )
    {
        if ( $_dyncss_quote_strings && ! is_numeric( $_dyncss_value ) )
        {
            $_dyncss_value = quote_string( $_dyncss_value );
        }
        $_dyncss_line = preg_replace( '#\$\{'.$_dyncss_key.'\}#i', str_replace(';', '\\;', $_dyncss_value), $_dyncss_line );
    }

    return $_dyncss_line;
}

function substitute_vars( $_dyncss_line, $_dyncss_vars = array(), $_dyncss_quote_strings = false )
{
    // substitute all delimited variables first
    $_dyncss_line = substitute_delimited_vars( $_dyncss_line, $_dyncss_vars, $_dyncss_quote_strings );
 
    // substitute all non-delimited variables last
    foreach ( $_dyncss_vars as $_dyncss_key => $_dyncss_value )
    {
        if ( $_dyncss_quote_strings && is_string( $_dyncss_value ) )
        {
            $_dyncss_value = quote_string( $_dyncss_value );
        }
        $_dyncss_line = preg_replace( '#\$'.$_dyncss_key.'#i', $_dyncss_value, $_dyncss_line );
    }

    return $_dyncss_line;
}

function strip_quotes( $_dyncss_value )
{
    $_dyncss_len = strlen( $_dyncss_value ) - 1;
    if ( $_dyncss_len < 1 )
    {
        return $_dyncss_value;
    }

    if ( $_dyncss_value[0] == "'" && $_dyncss_value[$_dyncss_len] == "'" )
    {
        $_dyncss_value = trim( $_dyncss_value, "'" );
    }
    elseif ( $_dyncss_value[0] == '"' && $_dyncss_value[$_dyncss_len] == '"' )
    {
        $_dyncss_value = trim( $_dyncss_value, '"' );
    }

    return $_dyncss_value;
}

function quote_string( $_dyncss_value )
{
    $_dyncss_len = strlen( $_dyncss_value ) - 1;
    if ( $_dyncss_len < 1 )
    {
        return "''";
    }

    if ( $_dyncss_value[0] == "'" && $_dyncss_value[$_dyncss_len] == "'" )
    {
        return $_dyncss_value;
    }
    elseif ( $_dyncss_value[0] == '"' && $_dyncss_value[$_dyncss_len] == '"' )
    {
        return $_dyncss_value;
    }

    return "'".addslashes( $_dyncss_value )."'";
}

function handle_pngs( $_dyncss_line )
{
    global $debug, $use_alpha_filter, $filter_sizing_method, $convert_to_png8, $matte_color;

// $_SERVER['HTTP_USER_AGENT'] = 'msie 5.6 winNT';

    $_dyncss_msie = '/msie\s(5\.[5-9]|6\.).*(win)/i';
    $_dyncss_msie_ok = '/msie\s[7-9].*(win)/i';
    $_dyncss_msie_no_filter = '/msie\s([0-4]\.|5\.[0-4]).*(win)/i';
    $_dyncss_opera = '/opera/i';

    if ( isset($_SERVER['HTTP_USER_AGENT']) )
    {
        if ( $use_alpha_filter
            && preg_match($_dyncss_msie, $_SERVER['HTTP_USER_AGENT'])
            && ! preg_match($_dyncss_msie_ok, $_SERVER['HTTP_USER_AGENT'])
            && ! preg_match($_dyncss_opera, $_SERVER['HTTP_USER_AGENT'])
            )
        {
            preg_match('/(.*):(.*)url\s*\(\s*[\'"]?([^\'"\);]*)[\'"]?\s*\)(.*)/', $_dyncss_line, $_dyncss_matches);
            if (count($_dyncss_matches) == 5)
            {
                $_dyncss_line = '';
                if ( strcasecmp( trim($_dyncss_matches[1]), 'background-image' ) !== 0 )
                {
                    $_dyncss_line .= $_dyncss_matches[1].':'.$_dyncss_matches[2].' '.$_dyncss_matches[4].';';
                }
                $_dyncss_line .= "zoom:100%;";
                $_dyncss_line .= "display:inline-block;";
                return $_dyncss_line.'filter:progid:DXImageTransform.Microsoft.AlphaImageLoader(src="'.$_dyncss_matches[3].'",sizingMethod="'.$filter_sizing_method.'",enabled="true");';
            }
            else
            {
                return $_dyncss_line;
            }
        }
        else
        {
            // browser too old to use filters - use gif/png8 image instead
            if ( ! preg_match($_dyncss_msie_ok, $_SERVER['HTTP_USER_AGENT'])
                && (( ! $use_alpha_filter
                        && preg_match($_dyncss_msie, $_SERVER['HTTP_USER_AGENT'])
                    )
                    || preg_match($_dyncss_msie_no_filter, $_SERVER['HTTP_USER_AGENT'])
                    )
                )
            {
                $_dyncss_img = preg_replace('/.*url\s*\(\s*[\'"]?([^\'"]*)[\'"]?\s*\).*/', '$1', $_dyncss_line);

                // try to figure out the background color
                $_dyncss_background_color = 'FFFFFF';
                preg_match('/.*:.*#([0-9a-fA-F]{3,6}).*/', $_dyncss_line, $_dyncss_matches);
                if (count($_dyncss_matches) == 2)
                {
                    $_dyncss_background_color = $_dyncss_matches[1];
                }

                // Convert to GIF or PNG8?
                if ($convert_to_png8)
                {
                    $_dyncss_new_img = preg_replace("/\.png/i", ".png8", $_dyncss_img);
                }
                else
                {
                    $_dyncss_new_img = preg_replace("/\.png/i", ".gif", $_dyncss_img);
                }

                if ( ! file_exists( $_dyncss_new_img ) )
                {
                    // gif file doesn't exist, let's try to create it
                    // Load Image and get it's size
                    $_dyncss_size = getimagesize($_dyncss_img);

                    // KG - removed because causes process to hang
                    // $_dyncss_required_memory = round($_dyncss_size[0] * $_dyncss_size[1] * 2 * $_dyncss_size['bits']);
                    // $_dyncss_new_limit = memory_get_usage() + $_dyncss_required_memory;
                    // ini_set("memory_limit", $_dyncss_new_limit);
                    
                    $_dyncss_image = imagecreatefrompng($_dyncss_img);
                    $_dyncss_image_result = imagecreatetruecolor($_dyncss_size[0],$_dyncss_size[1]);

                    if ( strlen( $matte_color ) == 3 )
                    {
                        $_dyncss_red = hexdec( $matte_color[0].$matte_color[0] );
                        $_dyncss_green = hexdec( $matte_color[1].$matte_color[1] );
                        $_dyncss_blue = hexdec( $matte_color[2].$matte_color[2] );
                    }
                    else
                    {
                        $_dyncss_red = hexdec( $matte_color[0].$matte_color[1] );
                        $_dyncss_green = hexdec( $matte_color[2].$matte_color[3] );
                        $_dyncss_blue = hexdec( $matte_color[4].$matte_color[5] );
                    }
                    $_dyncss_line .= '/* DEBUG: red='.$_dyncss_red.' green='.$_dyncss_green.' blue='.$_dyncss_blue.' */';
                    $_dyncss_bga = imagecolorallocate($_dyncss_image_result, $_dyncss_red, $_dyncss_green, $_dyncss_blue, 127); 
                    convert_alpha( $_dyncss_image, $_dyncss_red, $_dyncss_green, $_dyncss_blue );

                    if ($convert_to_png8)
                    {
                        imagefill($_dyncss_image_result, 0, 0, $_dyncss_bga); 
                        imagetruecolortopalette($_dyncss_image_result, false, 255);
                    }

                    imagecopyresampled($_dyncss_image_result,$_dyncss_image,0,0,0,0,$_dyncss_size[0],$_dyncss_size[1],$_dyncss_size[0],$_dyncss_size[1]);
                    imagecolortransparent($_dyncss_image_result, $_dyncss_bga);
                    // imagealphablending($_dyncss_image_result, true);
                    // imagesavealpha($_dyncss_image_result, true);

                    if ($convert_to_png8)
                    {
                        imagepng($_dyncss_image_result,$_dyncss_new_img);    
                    }
                    else
                    {
                        imagegif($_dyncss_image_result,$_dyncss_new_img);    
                    }
                    imagedestroy($_dyncss_image);
                    imagedestroy($_dyncss_image_result);
                    // ini_restore ("memory_limit");
                }
                $_dyncss_line = str_replace( $_dyncss_img, $_dyncss_new_img, $_dyncss_line );
            }
        }
    }

    return $_dyncss_line;
}

function convert_alpha( &$_dyncss_image, $_dyncss_bg_red=255, $_dyncss_bg_green=255, $_dyncss_bg_blue=255 )
{
    $_dyncss_height = imagesy( $_dyncss_image );
    $_dyncss_width = imagesx( $_dyncss_image );
    for($_dyncss_x = 0; $_dyncss_x < $_dyncss_width; $_dyncss_x++)
    {
        for($_dyncss_y = 0; $_dyncss_y < $_dyncss_height; $_dyncss_y++)
        {
            $_dyncss_color = imagecolorat( $_dyncss_image, $_dyncss_x, $_dyncss_y );
            $_dyncss_alpha = ( $_dyncss_color >>24 ) & 0x7F;
            $_dyncss_red   = ( $_dyncss_color >> 16 ) & 0xFF;
            $_dyncss_green = ( $_dyncss_color >> 8 ) & 0xFF;
            $_dyncss_blue  = $_dyncss_color & 0xFF;
            if ( $_dyncss_alpha > 0 && $_dyncss_alpha < 127 )
            {
                $_dyncss_alpha_float = (float)$_dyncss_alpha;
                $_dyncss_factor = (float)( (127.0 - $_dyncss_alpha_float) / 127.0 );
                $_dyncss_new_red = (float)$_dyncss_red * $_dyncss_factor +
                    (float)$_dyncss_bg_red * ( 1.0 - $_dyncss_factor );
                $_dyncss_new_green = (float)$_dyncss_green * $_dyncss_factor +
                    (float)$_dyncss_bg_green * ( 1.0 - $_dyncss_factor );
                $_dyncss_new_blue = (float)$_dyncss_blue * $_dyncss_factor +
                    (float)$_dyncss_bg_blue * ( 1.0 - $_dyncss_factor );
                if ( ( $_dyncss_new_color = imagecolorallocate( $_dyncss_image, (int)$_dyncss_new_red, (int)$_dyncss_new_green, (int)$_dyncss_new_blue ) ) !== false )
                {
                    imagesetpixel( $_dyncss_image ,$_dyncss_x, $_dyncss_y, $_dyncss_new_color );
                }
            }
        }
    }
}


ob_start ("filter");

/* for command line testing
$contents = file_get_contents( $_SERVER['REQUEST_URI'] );
echo $contents;
 */
