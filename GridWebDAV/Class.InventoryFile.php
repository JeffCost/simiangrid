<?php
/** Simian WebDAV service
 *
 * PHP version 5
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 *
 * @package    SimianGrid
 * @author     John Hurliman <http://software.intel.com/en-us/blogs/author/john-hurliman/>
 * @copyright  Open Metaverse Foundation
 * @license    http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @link       http://openmetaverse.googlecode.com/
 */

require_once 'Sabre.autoload.php';
class_exists('Curl') || require_once('Class.Curl.php');

class InventoryFile extends Sabre_DAV_File
{
    private $node;
    private $size;
    private $contentType;
    private $etag;
    private $fetched;

    function __construct($node)
    {
        $this->node = $node;
        $this->size = 0;
        $this->contentType = null;
        $this->etag = null;
        $this->fetched = false;
    }

    function initialize()
    {
        if (!$this->fetched)
        {
            global $config;
            $assetUrl = $config['asset_service'] . $this->node['AssetID'];
            
            $curl = new Curl();
            $curl->create($assetUrl);
            $curl->option(CURLOPT_HEADER, true);
            $curl->option(CURLOPT_NOBODY, true);
            
            $response = $curl->execute();
            if ($response)
            {
                $headers = http_parse_headers($response);
                
                $this->size = $headers['Content-Length'];
                $this->contentType = $headers['Content-Type'];
                $this->etag = $headers['ETag'];
            }
            
            $this->fetched = true;
        }
    }

    function getName()
    {
        return $this->node['Name'];
    }

    function get()
    {
        global $config;
        $assetUrl = $config['asset_service'] . $this->node['AssetID'];
        
        // Received a GET for this item. Redirect to the asset service
        header('Location: ' . $assetUrl);
        exit();
    }

    function getSize()
    {
        $this->initialize();
        return $this->size;
    }
    
    function getContentType()
    {
        $this->initialize();
        return $this->contentType;
    }
    
    function getETag()
    {
        $this->initialize();
        return $this->etag;
    }
}
