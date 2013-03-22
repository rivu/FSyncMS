<?php

# ***** BEGIN LICENSE BLOCK *****
# Version: MPL 1.1/GPL 2.0/LGPL 2.1
#
# The contents of this file are subject to the Mozilla Public License Version
# 1.1 (the 'License'); you may not use this file except in compliance with
# the License. You may obtain a copy of the License at
# http://www.mozilla.org/MPL/
#
# Software distributed under the License is distributed on an 'AS IS' basis,
# WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
# for the specific language governing rights AND limitations under the
# License.
#
# The Original Code is Weave Basic Object Server
#
# The Initial Developer of the Original Code is
# Mozilla Labs.
# Portions created by the Initial Developer are Copyright (C) 2008
# the Initial Developer. All Rights Reserved.
#
# Contributor(s):
#	
#	Tobias Hollerung (tobias@hollerung.eu)
#	Martin-Jan Sklorz (m.skl@lemsgbr.de)
#
# Alternatively, the contents of this file may be used under the terms of
# either the GNU General Public License Version 2 or later (the 'GPL'), or
# the GNU Lesser General Public License Version 2.1 or later (the 'LGPL'),
# in which case the provisions of the GPL or the LGPL are applicable instead
# of those above. If you wish to allow use of your version of this file only
# under the terms of either the GPL or the LGPL, AND not to allow others to
# use your version of this file under the terms of the MPL, indicate your
# decision by deleting the provisions above AND replace them with the notice
# AND other provisions required by the GPL or the LGPL. If you do not delete
# the provisions above, a recipient may use your version of this file under
# the terms of any one of the MPL, the GPL or the LGPL.
#
# ***** END LICENSE BLOCK *****

require_once 'weave_basic_object.php';
require_once 'weave_utils.php';
require_once 'settings.php';

class WeaveStorage
{
    private $_username;
    private $_dbh;
    
    private $_wbo_table;
    private $_users_table;

    function __construct($username) 
    {
        $this->_username = $username;

		$this->_wbo_table = $this->concatenate_prefix_with_table(DATABASE_TABLE_PREFIX, 'wbo');
		$this->_users_table = $this->concatenate_prefix_with_table(DATABASE_TABLE_PREFIX, 'users');

        log_error('Initalizing DB connecion!');
        
        try 
        {
        	switch (DATABASE_ENGINE)
        	{
        		case 'SQLITE': 
        		{
		            $path = explode('/', $_SERVER['SCRIPT_FILENAME']);

		            array_pop($path);
		            array_push($path, DATABASE_DB);
		            $db_name = implode('/', $path);

		            if (!file_exists($db_name)) 
		            {
		                log_error('The required sqlite database is not present! $db_name');
		            }

		            log_error('Starting SQLite connection');
		            $this->_dbh = new PDO('sqlite:' . $db_name);
		            $this->_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        			break;
        		}
        		case 'PGSQL': 
        		{
		            log_error('Starting PostgreSQL connection');
		            $this->_dbh = new PDO('pgsql:host='. DATABASE_HOST .';dbname='. DATABASE_DB, DATABASE_USER, DATABASE_PASSWORD);
        			break;
        		}
        		case 'MYSQL': 
        		{
		            log_error('Starting MySQL connection');
		            $this->_dbh = new PDO('mysql:host='. DATABASE_HOST .';dbname='. DATABASE_DB, DATABASE_USER, DATABASE_PASSWORD);
        			break;
        		}
        	}

        }
        catch (PDOException $exception) 
        {
            log_error('database unavailable ' . $exception->getMessage());
            throw new Exception('Database unavailable ' . $exception->getMessage() , 503);
        }

    }
    
    private function concatenate_prefix_with_table($prefix, $table_name)
    {
    	$return = '';
    	
    	if ($prefix)
    	{
    		$return = $prefix;
    	
			//add underscore if necessary
			$return .= ($prefix[strlen($prefix)-1] != '_') ? '_' : '';
			//add tablename
			$return .= $table_name;
    	}
    	else
    	{
    		$return = $table_name;
    	}
    	
    	return $return;
    }

    function get_connection()
    {
        return $this->_dbh;
    }

    function begin_transaction()
    {
        try
        {
            $this->_dbh->beginTransaction();
        }
        catch (PDOException $exception)
        {
            error_log('begin_transaction: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }
        return 1;
    }

    function commit_transaction()
    {
        $this->_dbh->commit();
        return 1;
    }

    function get_max_timestamp($collection)
    {
        if (!$collection)
        {
            return 0;
        }

        try
        {
            $select_stmt = 'SELECT MAX(modified) FROM ' . $this->_wbo_table . ' WHERE username = :username AND collection = :collection';
            $sth = $this->_dbh->prepare($select_stmt);
            $sth->bindParam(':username', $this->_username);
            $sth->bindParam(':collection', $collection);
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            error_log('get_max_timestamp: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }

        $result = $sth->fetchColumn();
        return ROUND((float)$result, 2);
    }

    function get_collection_list()
    {
        try
        {
            $select_stmt = 'SELECT DISTINCT(collection) FROM ' . $this->_wbo_table . ' WHERE username = :username';
            $sth = $this->_dbh->prepare($select_stmt);
            $sth->bindParam(':username', $this->_username);
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            error_log('get_collection_list: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }


        $collections = array();
        while ($result = $sth->fetchColumn())
        {
            $collections[] = $result;
        }

        return $collections;
    }


    function get_collection_list_with_timestamps()
    {
        try
        {
            $select_stmt = 'SELECT collection, max(modified) AS timestamp FROM ' . $this->_wbo_table . ' WHERE username = :username GROUP BY collection';
            $sth = $this->_dbh->prepare($select_stmt);
            $sth->bindParam(':username', $this->_username);
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            error_log('get_collection_list: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }

        $collections = array();
        while ($result = $sth->fetch(PDO::FETCH_NUM))
        {
            $collections[$result[0]] = (float)$result[1];
        }

        return $collections;
    }

    function get_collection_list_with_counts()
    {
        try
        {
            $select_stmt = 'SELECT collection, count(*) AS ct FROM ' . $this->_wbo_table . ' WHERE username = :username GROUP BY collection';
            $sth = $this->_dbh->prepare($select_stmt);
            $sth->bindParam(':username', $this->_username);
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            error_log('get_collection_list_with_counts: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }


        $collections = array();
        while ($result = $sth->fetch(PDO::FETCH_NUM))
        {
            $collections[$result[0]] = (int)$result[1];
        }

        return $collections;
    }


    function store_object(&$wbo)
    {
    	// object already exists -> update
		if ($this->exists_object($wbo->collection(), $wbo->id()))
		{
			return $this->update_object($wbo);
		}
		// -> insert
		else
		{
			return $this->create_object($wbo);
		}
	}


	function create_object(&$wbo)
	{
        try
        {
		    $insert_stmt = 'INSERT INTO ' . $this->_wbo_table . ' (username, id, collection, parentid, predecessorid, sortindex, modified, payload, payload_size)
		            VALUES (:username, :id, :collection, :parentid, :predecessorid, :sortindex, :modified, :payload, :payload_size)';
            $sth = $this->_dbh->prepare($insert_stmt);
		    $sth->bindParam(':username', $this->_username);
		    $sth->bindParam(':id', $wbo->id());
		    $sth->bindParam(':collection', $wbo->collection());
		    $sth->bindParam(':parentid', $wbo->parentid());
		    $sth->bindParam(':predecessorid', $wbo->predecessorid());
		    $sth->bindParam(':sortindex', $wbo->sortindex());
		    $sth->bindParam(':modified', $wbo->modified());
		    $sth->bindParam(':payload', $wbo->payload());
		    $sth->bindParam(':payload_size', $wbo->payload_size());
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            error_log('create_object: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }
        return 1;
	}
	
	
    function exists_object($collection, $id)
    {
        try
        {
            $select_stmt = 'SELECT * FROM ' . $this->_wbo_table . ' WHERE username = :username AND collection = :collection AND id = :id';
            $sth = $this->_dbh->prepare($select_stmt);
            $username = $this->_username;
            $sth->bindParam(':username', $username);
            $sth->bindParam(':collection', $collection);
            $sth->bindParam(':id', $id);
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            error_log('retrieve_object: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }

        if (!$result = $sth->fetch(PDO::FETCH_ASSOC))
        {
            return false;
        }
        else
        {
        	return true;
        }
    }


    function update_object(&$wbo)
    {
        $UPDATE= 'UPDATE' . $this->_wbo_table . ' SET ';
        $params = array();
        $update_list = array();

        //make sure we have an id and collection. No point in continuing otherwise
        if (!$wbo->id() || !$wbo->collection())
        {
            error_log('Trying to update without a valid id or collection!');
            return 0;
        }

        if ($wbo->parentid_exists())
        {
            $update_list[] = 'parentid = ?';
            $params[] = $wbo->parentid();
        }

        if ($wbo->predecessorid_exists())
        {
            $update_list[] = 'predecessorid = ?';
            $params[] = $wbo->predecessorid();
        }

        if ($wbo->sortindex_exists())
        {
            $update_list[] = 'sortindex = ?';
            $params[] = $wbo->sortindex();
        }

        if ($wbo->payload_exists())
        {
            $update_list[] = 'payload = ?';
            $update_list[] = 'payload_size = ?';
            $params[] = $wbo->payload();
            $params[] = $wbo->payload_size();
        }

		// Don't modify the timestamp on a non-payload/non-parent change change
        if ($wbo->parentid_exists() || $wbo->payload_exists())
        {
			//better make sure we have a modified date. Should have been handled earlier
            if (!$wbo->modified_exists())
            {
                error_log('Called update_object with no defined timestamp. Please check');
                $wbo->modified(microtime(1));
            }
            $update_list[] = 'modified = ?';
            $params[] = $wbo->modified();
        }


        if (count($params) == 0)
        {
            return 0;
        }

        $UPDATE.= join($update_list, ',');

        $UPDATE.= ' WHERE username = ? AND collection = ? AND id = ?';
        $params[] = $this->_username;
        $params[] = $wbo->collection();
        $params[] = $wbo->id();

        try
        {
            $sth = $this->_dbh->prepare($update);
            $sth->execute($params);
        }
        catch (PDOException $exception)
        {
            error_log('update_object: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }
        return 1;
    }


    function delete_object($collection, $id)
    {
        try
        {
            $delete_stmt = 'DELETE FROM ' . $this->_wbo_table . ' WHERE username = :username AND collection = :collection AND id = :id';
            $sth = $this->_dbh->prepare($delete_stmt);
            $username = $this->_username;
            $sth->bindParam(':username', $username);
            $sth->bindParam(':collection', $collection);
            $sth->bindParam(':id', $id);
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            error_log('delete_object: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }
        return 1;
    }

    function delete_objects($collection, $id = null, $parentid = null, $predecessorid = null, $newer = null,
            $older = null, $sort = null, $limit = null, $offset = null, $ids = null,
            $index_above = null, $index_below = null)
    {
        $params = array();
        $select_stmt = '';

        if ($limit || $offset || $sort)
        {
			//sqlite can't do sort or LIMITdeletes without special compiled versions
			//so, we need to grab the set, then DELETE it manually.

            $params = $this->retrieve_objects($collection, $id, 0, 0, $parentid, $predecessorid, $newer, $older, $sort, $limit, $offset, $ids, $index_above, $index_below);
            if (!count($params))
            {
                return 1; //nothing to delete
            }
            $paramqs = array();
            $select_stmt = 'DELETE FROM ' . $this->_wbo_table . ' WHERE username = ? AND collection = ? AND id IN (' . join(', ', array_pad($paramqs, count($params), '?')) . ')';
            array_unshift($params, $collection);
            array_unshift($params, $username);
        }
        else
        {

            $select_stmt = 'DELETE FROM ' . $this->_wbo_table . ' WHERE username = ? AND collection = ?';
            $params[] = $this->_username;
            $params[] = $collection;


            if ($id)
            {
                $select_stmt .= ' AND id = ?';
                $params[] = $id;
            }

            if ($ids && count($ids) > 0)
            {
                $qmarks = array();
                $select_stmt .= ' AND id IN (';
                foreach ($ids AS $temp)
                {
                    $params[] = $temp;
                    $qmarks[] = '?';
                }
                $select_stmt .= implode(',', $qmarks);
                $select_stmt .= ')';
            }

            if ($parentid)
            {
                $select_stmt .= ' AND parentid = ?';
                $params[] = $parentid;
            }

            if ($predecessorid)
            {
                $select_stmt .= ' AND predecessorid = ?';
                $params[] = $parentid;
            }

            if ($index_above)
            {
                $select_stmt .= ' AND sortindex > ?';
                $params[] = $parentid;
            }

            if ($index_below)
            {
                $select_stmt .= ' AND sortindex < ?';
                $params[] = $parentid;
            }

            if ($newer)
            {
                $select_stmt .= ' AND modified > ?';
                $params[] = $newer;
            }

            if ($older)
            {
                $select_stmt .= ' AND modified < ?';
                $params[] = $older;
            }

            if ($sort == 'index')
            {
                $select_stmt .= ' ORDER BY sortindex DESC';
            }
            else if ($sort == 'newest')
            {
                $select_stmt .= ' ORDER BY modified DESC';
            }
            else if ($sort == 'oldest')
            {
                $select_stmt .= ' ORDER BY modified';
            }

        }

        try
        {
            $sth = $this->_dbh->prepare($select_stmt);
            $sth->execute($params);
        }
        catch (PDOException $exception)
        {
            error_log('delete_objects: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }
        return 1;
    }


    function retrieve_object($collection, $id)
    {
        try
        {
            $select_stmt = 'SELECT * FROM ' . $this->_wbo_table . ' WHERE username = :username AND collection = :collection AND id = :id';
            $sth = $this->_dbh->prepare($select_stmt);
            $username = $this->_username;
            $sth->bindParam(':username', $username);
            $sth->bindParam(':collection', $collection);
            $sth->bindParam(':id', $id);
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            error_log('retrieve_object: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }

        $result = $sth->fetch(PDO::FETCH_ASSOC);

        $wbo = new wbo();
        $wbo->populate($result);
        return $wbo;
    }

    function retrieve_objects($collection, $id = null, $full = null, $direct_output = null, $parentid = null,
            $predecessorid = null, $newer = null, $older = null, $sort = null,
            $limit = null, $offset = null, $ids = null,
            $index_above = null, $index_below = null)
    {
        $full_list = $full ? '*' : 'id';


        $select_stmt = 'SELECT '.$full_list.' FROM ' . $this->_wbo_table . ' WHERE username = ? AND collection = ?';
        $params[] = $this->_username;
        $params[] = $collection;


        if ($id)
        {
            $select_stmt .= ' AND id = ?';
            $params[] = $id;
        }

        if ($ids && count($ids) > 0)
        {
            $qmarks = array();
            $select_stmt .= ' AND id IN (';
            foreach ($ids AS $temp)
            {
                $params[] = $temp;
                $qmarks[] = '?';
            }
            $select_stmt .= implode(',', $qmarks);
            $select_stmt .= ')';
        }

        if ($parentid)
        {
            $select_stmt .= ' AND parentid = ?';
            $params[] = $parentid;
        }


        if ($predecessorid)
        {
            $select_stmt .= ' AND predecessorid = ?';
            $params[] = $predecessorid;
        }

        if ($index_above)
        {
            $select_stmt .= ' AND sortindex > ?';
            $params[] = $parentid;
        }

        if ($index_below)
        {
            $select_stmt .= ' AND sortindex < ?';
            $params[] = $parentid;
        }

        if ($newer)
        {
            $select_stmt .= ' AND modified > ?';
            $params[] = $newer;
        }

        if ($older)
        {
            $select_stmt .= ' AND modified < ?';
            $params[] = $older;
        }

        if ($sort == 'index')
        {
            $select_stmt .= ' ORDER BY sortindex DESC';
        }
        else if ($sort == 'newest')
        {
            $select_stmt .= ' ORDER BY modified DESC';
        }
        else if ($sort == 'oldest')
        {
            $select_stmt .= ' ORDER BY modified';
        }

        if ($limit)
        {
            $select_stmt .= ' LIMIT' . intval($limit);
            if ($offset)
            {
                $select_stmt .= ' OFFSET ' . intval($offset);
            }
        }

        try
        {
            $sth = $this->_dbh->prepare($select_stmt);
            $sth->execute($params);
        }
        catch (PDOException $exception)
        {
            error_log('retrieve_collection: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }

        if ($direct_output)
            return $direct_output->output($sth);

        $ids = array();
        while ($result = $sth->fetch(PDO::FETCH_ASSOC))
        {
            if ($full)
            {
                $wbo = new wbo();
                $wbo->populate($result);
                $ids[] = $wbo;
            }
            else
            {
                $ids[] = $result{'id'};
            }
        }
        return $ids;
    }


    function get_storage_total()
    {
    	log_error('get_storage_total');
        try
        {
            $select_stmt = 'SELECT ROUND(SUM(LENGTH(payload))/1024) FROM ' . $this->_wbo_table . ' WHERE username = :username';
            $sth = $this->_dbh->prepare($select_stmt);
            $username = $this->_username;
            $sth->bindParam(':username', $username);
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            error_log('get_storage_total: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }

        return (int)$sth->fetchColumn();
    }


    function get_collection_storage_totals()
    {
        log_error('get_collection_storage_totals');
        try
        {
            $select_stmt = 'SELECT collection, SUM(payload_size) FROM ' . $this->_wbo_table . ' WHERE username = :username GROUP BY collection';
            $sth = $this->_dbh->prepare($select_stmt);
            $username = $this->_username;
            $sth->bindParam(':username', $username);
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            error_log('get_storage_total (' . $this->connection_details_string() . '): ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }
        $results = $sth->fetchAll(PDO::FETCH_NUM);
        $sth->closeCursor();

        $collections = array();
        foreach ($results AS $result)
        {
            $collections[$result[0]] = (int)$result[1];
        }
        return $collections;
    }


    function get_user_quota()
    {
    	log_error('get_user_quota: not implemented');
        return null;
    }

    function delete_storage($username)
    {
        log_error('delete_storage');
        if (!$username)
        {
            throw new Exception('3', 404);
        }
        try
        {
            $delete_stmt = 'DELETE FROM ' . $this->_wbo_table . ' WHERE username = :username';
            $sth = $this->_dbh->prepare($delete_stmt);
            $sth->bindParam(':username', $username);
            $sth->execute();
            $sth->closeCursor();
        }
        catch (PDOException $exception)
        { 
            error_log('delete_storage: ' . $exception->getMessage());
            return 0;
        } 
        return 1;

    }

    function delete_user($username)
    {
        log_error('delete_user');
        if (!$username)
        {
            throw new Exception('3', 404);
        }

        try
        {
            $delete_stmt = 'DELETE FROM ' . $this->_users_table . ' WHERE username = :username';
            $sth = $this->_dbh->prepare($delete_stmt);
            $sth->bindParam(':username', $username);
            $sth->execute();
            $sth->closeCursor();

            $delete_wbo_stmt = 'DELETE FROM ' . $this->_wbo_table . ' WHERE username = :username';
            $sth = $this->_dbh->prepare($delete_wbo_stmt);
            $sth->bindParam(':username', $username);
            $sth->execute();

        }
        catch (PDOException $exception)
        {
            error_log('delete_user: ' . $exception->getMessage());
            return 0;
        }
        return 1;
    }

    function create_user($username, $password)
    {
        log_error('create_user: ' . $this->_username);
        try
        {
            $create_statement = 'INSERT INTO ' . $this->_users_table . ' VALUES (:username, :md5)';

            $sth = $this->_dbh->prepare($create_statement);
            $sth->bindParam(':username', $username);
            $sth->bindParam(':md5', md5($password));
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            log_error('create_user: '.$exception->getMessage());
            error_log('create_user: '.$exception->getMessage());
            return 0;
        }
        return 1;
    }

    function change_password($username, $password)
    {
        log_error('change_password: ' . $this->_username);
        try
        {
            $update_statement = 'UPDATE' . $this->_users_table . ' SET md5 = :md5 WHERE username = :username';

            $sth = $this->_dbh->prepare($update_statement);
            $password = md5($password);
            $sth->bindParam(':username', $username);
            $sth->bindParam(':md5', $password);
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            log_error('change_password: '.$exception->getMessage());
            return 0;
        }
        return 1;
    }

    function exists_user()
    {
        try
        {
            $select_stmt = 'SELECT username FROM ' . $this->_users_table . ' WHERE username = :username';
            $sth = $this->_dbh->prepare($select_stmt);
            $username = $this->_username;
            $sth->bindParam(':username', $username);
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            error_log('exists_user: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }

        if (!$result = $sth->fetch(PDO::FETCH_ASSOC))
        {
            return null;
        }
        return 1;
    }


    function authenticate_user($password)
    {
        log_error('authenticate_user: ' . $this->_username);
        try
        {
            $select_stmt = 'SELECT username FROM ' . $this->_users_table . ' WHERE username = :username AND md5 = :md5';
            $sth = $this->_dbh->prepare($select_stmt);
            $username = $this->_username;
            $password = md5($password);
            $sth->bindParam(':username', $username);
            $sth->bindParam(':md5', $password);
            $sth->execute();
        }
        catch (PDOException $exception)
        {
            error_log('authenticate_user: ' . $exception->getMessage());
            throw new Exception('Database unavailable', 503);
        }

        if (!$result = $sth->fetch(PDO::FETCH_ASSOC))
        {
            return null;
        }

        return 1;
    }

}


?>
