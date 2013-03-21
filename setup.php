<?php

# ***** BEGIN LICENSE BLOCK *****
# Version: MPL 1.1/GPL 2.0/LGPL 2.1
# 
# The contents of this file are subject to the Mozilla Public License Version 
# 1.1 (the "License"); you may not use this file except in compliance with 
# the License. You may obtain a copy of the License at 
# http://www.mozilla.org/MPL/
# 
# Software distributed under the License is distributed on an "AS IS" basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
# for the specific language governing rights and limitations under the
# License.
# 
# The Original Code is Weave Minimal Server
# 
# The Initial Developer of the Original Code is
#   Stefan Fischer
# Portions created by the Initial Developer are Copyright (C) 2012
# the Initial Developer. All Rights Reserved.
# 
# Contributor(s):
# Tobias Hollerung (tobias@hollerung.eu)
# 
# Alternatively, the contents of this file may be used under the terms of
# either the GNU General Public License Version 2 or later (the "GPL"), or
# the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
# in which case the provisions of the GPL or the LGPL are applicable instead
# of those above. If you wish to allow use of your version of this file only
# under the terms of either the GPL or the LGPL, and not to allow others to
# use your version of this file under the terms of the MPL, indicate your
# decision by deleting the provisions above and replace them with the notice
# and other provisions required by the GPL or the LGPL. If you do not delete
# the provisions above, a recipient may use your version of this file under
# the terms of any one of the MPL, the GPL or the LGPL.
# 
# ***** END LICENSE BLOCK *****

// --------------------------------------------
// variables start
// --------------------------------------------
$action = null;
$db_type = null;

$db_user = null;
$db_name = null;
$db_pass = null;
$db_host = null;
$db_typeablePrefix = null;
// --------------------------------------------
// variables end
// --------------------------------------------


// --------------------------------------------
// post handling start
// --------------------------------------------
if (isset($_POST['action']))
{
    $action = check_input($_POST['action']);
}

if (isset($_POST['dbtype']))
{
    $db_type = check_input($_POST['dbtype']);
}

if (isset($_POST['dbhost']))
{
    $db_host = check_input($_POST['dbhost']);
}

if (isset($_POST['dbname']))
{
    $db_name = check_input($_POST['dbname']);
}

if (isset($_POST['dbuser']))
{
    $db_user = check_input($_POST['dbuser']);
}

if (isset($_POST['dbpass']))
{
    $db_pass = check_input($_POST['dbpass']);
}

if (isset($_POST['dbtableprefix']))
{
    $db_typeablePrefix = check_input($_POST['dbtableprefix']);
}
// --------------------------------------------
// post handling end
// --------------------------------------------


// --------------------------------------------
// functions start
// --------------------------------------------

/*
    ensure that the input is not total waste
*/
function check_input( $data )
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}


/*
    create the config file with the database type
    and the given connection credentials
*/
function write_config_file($db_type, $db_host, $db_name, $db_user, $db_pass, $fsRoot, $db_typeablePrefix)
{

    // construct the name of config file
    $path = explode('/', $_SERVER['SCRIPT_FILENAME']);
    array_pop($path);
    array_push($path, 'settings.php');
    $cfg_file_name = implode('/', $path);

    if (file_exists($cfg_file_name) && filesize( $cfg_file_name ) > 0)
    {
        echo '<hr>The config file $cfg_file_name is already present</hr>';
        return;
    }

    echo 'Creating cfg file: ' . $cfg_file_name;

    // now build the content of the config file
    $cfg_content  = '<?php\n\n';
    $cfg_content .= '    // you can disable registration to the firefox sync server here,\n';
    $cfg_content .= '    // by setting ENABLE_REGISTER to false\n';
    $cfg_content .= '    // \n';
    $cfg_content .= '    define("ENABLE_REGISTER", true);\n\n';

    $cfg_content .= '    // firefox sync server url, this should end with a /\n';
    $cfg_content .= '    // e.g. https://YourDomain.de/Folder_und_ggf_/index.php/\n';
    $cfg_content .= '    // \n';
    $cfg_content .= '    define("FSYNCMS_ROOT", "' . $fsRoot . '");\n\n';

    $cfg_content .= '    // database connection details\n';
    $cfg_content .= '    // \n';
    $cfg_content .= '    // \n';
    $cfg_content .= '    // database system you want to use\n';
    $cfg_content .= '    // e.g. MYSQL, PGSQL, SQLITE\n';
    $cfg_content .= '    define("DATABASE_ENGINE", "' . $db_type . '")\n';
    $cfg_content .= '    // \n';
    $cfg_content .= '    define("DATABASE_HOST", "' . $db_host . '");\n';
    $cfg_content .= '    define("DATABASE_DB", "' . $db_name . '");\n';
    $cfg_content .= '    define("DATABASE_USER", "' . $db_user . '");\n';
    $cfg_content .= '    define("DATABASE_PASSWORD", "' . $db_pass . '");\n';
    $cfg_content .= '    \n';
    $cfg_content .= '    define("DATABASE_TABLE_PREFIX", "' . $db_typeablePrefix . '");\n';
    
    $cfg_content .= '\n?>\n';

    // now write everything
    $cfg_file = fopen($cfg_file_name, 'a');
    fputs($cfg_file, $cfg_content);
    fclose($cfg_file);
}


function echo_header($title)
{
    if (!isset($title)) {
        $title = '';
    }
    echo '<!DOCTYPE html>
		<html lang="en">
    		<head>
    			<title>' . $title . '</title>
    		</head>
    		
    		<body>
    			<h1>Setup FSyncMS</h1>';
}
function echo_form_header()
{
	echo '<form action="setup.php" method="post">';
}
function echo_form_footer()
{
	echo '</form>';
}
function echo_footer()
{
    echo '	</body>
    	</html>';
}

// --------------------------------------------
// functions end
// --------------------------------------------


	// check if we have no configuration at the moment
	if (file_exists('settings.php') && filesize('settings.php') > 0 )
	{
		echo '<hr><h2>The setup looks completed, please finish it by deleting settings.php</h2><hr>';
		exit;
	}


	// step 1 - select the database engine
	if (!$action)
	{
		// first check if we have pdo installed (untested)
		if (!extension_loaded('PDO'))
		{
		    echo 'ERROR - PDO is missing in the php installation!';
		    exit();
		}
		$valid_pdo_driver = 0;

		echo_header('Setup FSyncMS - DB engine selection');

		echo 'Which database type should be used?<br/>';
		
		echo_form_header();
		
		//SQLite
		if (extension_loaded('pdo_sqlite'))
		{
		    echo '<input type="radio" name="dbType" value="sqlite" checked="checked" /> SQLite<br/>';
		    $valid_pdo_driver++;
		}
		else
		{
		    echo 'SQLite not possible (driver missing)!<br/>';
		}
		
		//PostgreSQL
		if (extension_loaded('pdo_pgsql'))
		{
		    echo '<input type="radio" name="dbType" value="pgsql" /> PostgreSQL<br/>';
		    $valid_pdo_driver++;
		}
		else
		{
		    echo 'MySQL not possible (driver missing)!<br/>';
		}
		
		//MySQL
		if (extension_loaded('pdo_mysql'))
		{
		    echo '<input type="radio" name="dbType" value="mysql" /> MySQL<br/>';
		    $valid_pdo_driver++;
		}
		else
		{
		    echo 'PostgreSQL not possible (driver missing)!<br/>';
		}


		if ($valid_pdo_driver < 1)
		{
		    echo '<hr> No valid pdo driver found! Please install a valid pdo driver first <hr>';
		}
		else
		{
		    echo '<input type="hidden" name="action" value="step2">
		    <p><input type="submit" value="OK" /></p>';
		    echo_form_footer();
		}
		
		echo_footer();
	};


	// step 2 - database details
	if ($action == 'step2')
	{
		// first check if we have valid data
		if (!extension_loaded('PDO'))
		{
		    echo 'ERROR - This type of database (' . $dbType . ') is not valid at the moment!';
		    exit();
		}
		
		echo_header('Setup FSyncMS - DB settings: ' . $db_type);
		
		echo_form_header();
		
		echo '<table>
					<tr>
						<td>Instance name</td>
						<td><input type="text" name="dbname" /></td>
					</tr>';
					
		if ($db_type != 'sqlite')
		{
			echo '	<tr>
						<td>Host</td>
						<td><input type="text" name="dbhost" /></td>
					</tr>
					<tr>
						<td>Username</td>
						<td><input type="text" name="dbuser" /></td>
					</tr>
					<tr>
						<td>Password</td>
						<td><input type="password" name="dbpass" /></td>
					</tr>
					<tr>
						<td>Table Prefix</td>
						<td><input type="text" name="dbtableprefix" /></td>
					</tr>';
		}
					
		echo '	</table>

				<input type="hidden" name="action" value="step3">
				<input type="hidden" name="dbtype" value="'. $db_type .'">
				<p><input type="submit" value="OK"></p>';
		
		echo_form_footer();
		
		echo_footer();
	}


	// step 3 - create the database
	if ($action == 'step3')
	{
		echo_header('Setup FSyncMS - DB setup: ' . $db_type);
		
		$db_installed = false;
		$db_handle = null;
		try
		{
			switch ($db_type)
			{
				case 'sqlite':
				    $path = explode('/', $_SERVER['SCRIPT_FILENAME']);
				    array_pop($path);
				    array_push($path, $db_name);
				    $db_name = implode('/', $path);

				    if (file_exists($db_name) && filesize($db_name) > 0)
				    {
				        $db_installed = true;
				    }
				    else
				    {
				        echo("Creating sqlite weave storage: ". $db_name ."<br/>");
				        $db_handle = new PDO('sqlite:' . $db_name);
				        $db_handle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				    }
					break;
				case 'pgsql':
				    $db_handle = new PDO("pgsql:host=". $db_host .";dbname=". $db_name, $db_user, $db_pass);
	
				    $sth = $db_handle->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = \'public\' AND table_name = ' . $db_typeablePrefix . 'wbo;');
				    $sth->execute();
				    
				    $count = $sth->rowCount();
				    if ($count > 0)
				    {
				        $db_installed = true;
				    }
				    break;
				case 'mysql':
				    $db_handle = new PDO("mysql:host=". $db_host .";dbname=". $db_name, $db_user, $db_pass);
	
				    $sth = $db_handle->prepare('SHOW TABLES LIKE ' . $db_typeablePrefix . 'wbo;');
				    $sth->execute();
				    
				    $count = $sth->rowCount();
				    if ($count > 0)
				    {
				        $db_installed = true;
				    }
				    break;
			}
		}
		catch (PDOException $exception)
		{
		    echo('Database unavailable ' . $exception->getMessage());
		    throw new Exception('Database unavailable ' . $exception->getMessage() , 503);
		}

		if ($db_installed)
		{
		    echo 'DB is already installed!<br/>';
		}
		else
		{
		    echo 'Now going to install the new database! Type is: $db_type<br/>';

		    try
		    {
		        $create_statement = 'CREATE TABLE ' . $db_typeablePrefix . 'wbo ( username varchar(100), id varchar(65), collection varchar(100),
		             parentid  varchar(65), predecessorid int, modified real, sortindex int,
		             payload text, payload_size int, ttl int, primary key (username,collection,id));';
		        $create_statement2 = 'CREATE TABLE ' . $db_typeablePrefix . 'users ( username varchar(255), md5 varchar(64), primary key (username));';
		        $index1 = 'create index parentindex on ' . $db_typeablePrefix . 'wbo (username, parentid);';
		        $index2 = 'create index predecessorindex on ' . $db_typeablePrefix . 'wbo (username, predecessorid);';
		        $index3 = 'create index modifiedindex on ' . $db_typeablePrefix . 'wbo (username, collection, modified);';

		        $sth = $db_handle->prepare($create_statement);
		        $sth->execute();
		        $sth = $db_handle->prepare($create_statement2);
		        $sth->execute();
		        $sth = $db_handle->prepare($index1);
		        $sth->execute();
		        $sth = $db_handle->prepare($index2);
		        $sth->execute();
		        $sth = $db_handle->prepare($index3);
		        $sth->execute();
		        echo 'Database created<br/>';
		    }
		    catch(PDOException $exception)
		    {
		        throw new Exception('Database unavailable', 503);
		    }  

		}

		// get the FSYNC_ROOT url
		$fsRoot ='https://';
		if (!isset($_SERVER['HTTPS']))
		{ 
		    $fsRoot = 'http://';
		}   
		$fsRoot .= $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
		if(strpos( $_SERVER['REQUEST_URI'], 'index.php') !== 0)
		{ 
		    $fsRoot .= 'index.php/';
		}   

		// write settings.php, if not possible, display the needed content
		write_config_file($db_type, $db_host, $db_name, $db_user, $db_pass, $fsRoot, $db_typeablePrefix);

		echo '<hr><hr> Finished the setup, please delete setup.php and go on with the FFSync<hr><hr>';
		echo '<hr><hr> 
			<h4>This script has guessed the Address of your installation, this might not be accurate,<br/>
		    Please check if this script can be reached by <a href="$fsRoot">$fsRoot</a>.<br/>
		    If thats not the case you have to ajust the settings.php<br />
		    </h4>';
	
		echo_footer();
	}

?>
