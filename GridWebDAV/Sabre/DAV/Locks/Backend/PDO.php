<?php

/**
 * The Lock manager allows you to handle all file-locks centrally.
 *
 * This Lock Manager stores all its data in a database. You must pass a PDO
 * connection object in the constructor.
 * 
 * @package Sabre
 * @subpackage DAV
 * @version $Id: FS.php 535 2009-08-07 17:35:17Z evertpot $
 * @copyright Copyright (C) 2007-2010 Rooftop Solutions. All rights reserved.
 * @author Evert Pot (http://www.rooftopsolutions.nl/) 
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class Sabre_DAV_Locks_Backend_PDO extends Sabre_DAV_Locks_Backend_Abstract {

    /**
     * The PDO connection object 
     * 
     * @var pdo 
     */
    private $pdo;

    public function __construct(PDO $pdo) {

        $this->pdo = $pdo;

    }

    /**
     * Returns a list of Sabre_DAV_Locks_LockInfo objects  
     * 
     * This method should return all the locks for a particular uri, including
     * locks that might be set on a parent uri.
     *
     * @param string $uri 
     * @return array 
     */
    public function getLocks($uri) {

        // NOTE: the following 10 lines or so could be easily replaced by 
        // pure sql. MySQL's non-standard string concatination prevents us
        // from doing this though.

        $query = 'SELECT owner, token, timeout, created, scope, depth, uri FROM locks WHERE (created + timeout > ?) AND ((uri = ?)';
        $params = array(time(),$uri);

        // We need to check locks for every part in the uri.
        $uriParts = explode('/',$uri);

        // We already covered the last part of the uri
        array_pop($uriParts);

        $currentPath='';

        foreach($uriParts as $part) {

            if ($currentPath) $currentPath.='/';
            $currentPath.=$part;

            $query.=' OR (depth!=0 AND uri = ?)';
            $params[] = $part;

        }

        $query.=')';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll();

        $lockList = array();
        foreach($result as $row) {

            $lockInfo = new Sabre_DAV_Locks_LockInfo();
            $lockInfo->owner = $row['owner'];
            $lockInfo->token = $row['token'];
            $lockInfo->timeout = $row['timeout'];
            $lockInfo->created = $row['created'];
            $lockInfo->scope = $row['scope'];
            $lockInfo->depth = $row['depth'];
            $lockInfo->uri   = $row['uri'];
            $lockList[] = $lockInfo;

        }

        return $lockList;

    }

    /**
     * Locks a uri 
     * 
     * @param string $uri 
     * @param Sabre_DAV_Locks_LockInfo $lockInfo 
     * @return bool 
     */
    public function lock($uri,Sabre_DAV_Locks_LockInfo $lockInfo) {

        // We're making the lock timeout 30 minutes
        $lockInfo->timeout = 30;
        $lockInfo->created = time();
        $lockInfo->uri = $uri;

        $locks = $this->getLocks($uri);
        $exists = false;
        foreach($locks as $k=>$lock) {
            if ($lock->token == $lockInfo->token) $exists = true;
        }
        
        if ($exists) {
            $stmt = $this->pdo->prepare('UPDATE locks SET owner = ?, timeout = ?, scope = ?, depth = ?, uri = ? WHERE token = ?');
            $stmt->execture($lockInfo->owner,$lockInfo->timeout,$lockInfo->scope,$lockInfo->depth,$uri,$lockInfo->token);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO locks (owner,timeout,scope,depth,uri,token) VALUES (?,?,?,?,?,?)');
            $stmt->execture($lockInfo->owner,$lockInfo->timeout,$lockInfo->scope,$lockInfo->depth,$uri,$lockInfo->token);
        }

        return true;

    }



    /**
     * Removes a lock from a uri 
     * 
     * @param string $uri 
     * @param Sabre_DAV_Locks_LockInfo $lockInfo 
     * @return bool 
     */
    public function unlock($uri,Sabre_DAV_Locks_LockInfo $lockInfo) {

        $stmt = $this->pdo->prepare('DELETE FROM locks WHERE uri = ? AND token = ?');
        $stmt->execure(array($uri,$lockInfo->token));

    }

}

