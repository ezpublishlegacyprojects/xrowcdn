<?php

class xrowCDN
{

    /* Gets the CDN handler 
     *      
     * @param $handlerName Defines the handlername default: xrowCloudFront
     * @throws Exception When an error occured
     */
    static function &getInstance( $handlerName = 'xrowCloundFront' )
    {
        if ( ! array_key_exists( 'xrowCDNInstance', $GLOBALS ) )
        {
            $xrowCDNini = eZINI::instance( 'xrowcdn.ini' );
            $optionArray = array( 
                'iniFile' => 'xrowcdn.ini' , 
                'iniSection' => 'Settings' , 
                'iniVariable' => 'ImplementationAlias' , 
                'handlerIndex' => $handlerName , 
                'handlerParams' => array( 
                    $xrowCDNini 
                ) 
            );
            
            $options = new ezpExtensionOptions( $optionArray );
            
            $impl = eZExtension::getHandlerClass( $options );
            if ( ! $impl )
            {
                throw new Exception( "CDN Handler \"$handlerName\" not loaded" );
            }
            $GLOBALS['xrowCDNInstance'] = &$impl;
        }
        else
        {
            $impl = & $GLOBALS['xrowCDNInstance'];
        }
        return $impl;
    }

    /* Gets latest update DateTime of distribution
     *      
     * @throws Exception When an error occured
     */
    static function getLatestUpdateDistribution()
    {
        $name = "xrowcdn_distribution_time";
        $value = eZSiteData::get( $name );
        if ( $value !== false )
        {
            return new DateTime( $value );
        }
        else
        {
            return new DateTime( '1970-01-01 00:00:00' );
        }
    }

    /* Gets latest update DateTime of database
     *      
     * @throws Exception When an error occured
     */
    static function getLatestUpdateDatabase()
    {
        $name = "xrowcdn_database_time";
        $value = eZSiteData::get( $name );
        if ( $value !== false )
        {
            return new DateTime( $value );
        }
        else
        {
            return new DateTime( '1970-01-01 00:00:00' );
        }
    }

    /* Clears all namespaces
     *      
     * @throws Exception When an error occured
     */
    static function cleanAll( $namespace )
    {
    	$ini = eZINI::instance( 'xrowcdn.ini' );
        $cdn = xrowCDN::getInstance();
        $newtime = new DateTime( '1970-01-01T00:00:00' );
        xrowCDN::setLatestDistributionUpdate( $newtime );
        xrowCDN::setLatestDatabaseUpdate( $newtime );
        if ( $ini->hasVariable( 'Rules', 'List' ) )
        {
            foreach ( $ini->variable( 'Rules', 'List' ) as $rule )
            {
                $dirs = array();
                $suffix = array();
                
                if ( $ini->hasSection( 'Rule-' . $rule ) )
                {
                    $bucket = $ini->variable( 'Rule-' . $rule, 'Bucket' );
                    $cdn->clean( $bucket );
                }
            } // foreach
        } // if has Rules List
    

    }

    /* Clears the namespace
     *      
     * @param $namespace Defines the namespace
     * @throws Exception When an error occured
     */
    static function clean( $namespace )
    {
        $cdn = xrowCDN::getInstance();
        $cdn->clean( $namespace );
    }

    /* Wrapper to set latest Distribution files update DateTime
     *      
     * @param DateTime $since Defines the latest DateTime of update
     * @throws Exception When an error occured
     */
    static function setLatestDistributionUpdate( DateTime $since = null )
    {
    	$name = "xrowcdn_distribution_time";
    	return eZSiteData::set( $name, $datetime->format( DateTime::ISO8601 ) );
    }

    /* Wrapper to set latest Database files update DateTime
     *      
     * @param DateTime $since Defines the latest DateTime of update
     * @throws Exception When an error occured
     */
    static function setLatestDatabaseUpdate( DateTime $since = null )
    {
    	$name = "xrowcdn_database_time";
        return eZSiteData::set( $name, $datetime->format( DateTime::ISO8601 ) );
    }

    /* Updates Binary and Distribution
	 *      
	 * @param DateTime $since Defines the point of time from when the files should be updated
     * @throws Exception When an error occured
	 */
    static function update( DateTime $since = null )
    {
        self::updateDistribution( $since );
        self::updateDatabaseFiles( $since );
    }

    /**
     * Update files from design/* extension/* var/cahe/public share/icons
     *
     * @param DateTime $since Defines the point of time from when the files should be updated
     * @throws Exception When an error occured
     */
    static function updateDistributionFiles( DateTime $since = null )
    {
        $cdn = xrowCDN::getInstance();
        $ignoreExistance = true;
        if ( ! ( $since instanceof DateTime ) )
        {
            $since = new DateTime( '1970-01-01 00:00:00' );
        }
        
        $cli = eZCLI::instance();
        $ini = eZINI::instance( 'xrowcdn.ini' );
        
        $cli->output( 'Running updateDistribution...' );
        $countfiles = 0;
        $countfiles_up = 0;
        $countfiles_ok = 0;
        $countfiles_out = 0;
        $files = array();
        $filestoupload = array();
        $bucketlist = array();
        $bucketfiles = array();
        
        $allfiles = xrowCDN::getDistributionFiles();
        $countfiles = $allfiles["count"];
        
        // We can get all files from the bucket here and check if the files to upload exists on the remote location or not.
        // $ignoreExistance can manage that
        

        #foreach ( $allfiles["buckets"] as $bucketitem )
        #{
        #    $bucketfiles[$bucketitem] = $cdn->getAllDistributedFiles( $bucketitem );
        #}
        

        foreach ( $allfiles["files"] as $uploadfile )
        {
            if ( $ignoreExistance or in_array( str_replace( "\\", "/", $uploadfile["file"] ), $bucketfiles[$uploadfile["bucket"]] ) )
            {
                #$info = $this->s3->getInfo( $uploadfile["bucket"] . "/" . str_replace( "\\", "/", $uploadfile["file"]) );
                $filetime = filemtime( $uploadfile["file"] );
                $filetime = new DateTime( "@{$filetime}" );
                if ( $filetime > $since )
                {
                    $countfiles_out ++;
                }
                else
                {
                    $countfiles_ok ++;
                    continue;
                }
            }
            
            $cli->output( "[UPLOAD] " . $uploadfile["bucket"] . "/" . str_replace( "\\", "/", $uploadfile["file"] ) . ' modified ' . $filetime->format( DateTime::ISO8601 ) );
            $countfiles_up ++;
            $cdn->put( $uploadfile["file"], str_replace( "\\", "/", $uploadfile["file"] ), $uploadfile["bucket"] );
        
        }
        $cli->output( "--- Result ---" );
        $cli->output( "$countfiles files checked total." );
        $cli->output( "$countfiles_up files uploaded." );
        $cli->output( "updateDistribution finished..." );
        $cli->output( "" );
    }

    /**
     * Updates files in the database
     * @param DateTime $since Defines the point of time from when the files should be updated
     * @throws Exception When an error occured
     */
    static function updateDatabaseFiles( DateTime $since = null )
    {
        $cdn = xrowCDN::getInstance();
        $ignoreExistance = true;
        if ( ! ( $since instanceof DateTime ) )
        {
            $since = new DateTime( '1970-01-01 00:00:00' );
        }
        $db_timestamp = strtotime( $since->format( DateTime::ISO8601 ) );
        $cli = eZCLI::instance();
        $ini = eZINI::instance( 'xrowcdn.ini' );
        $cli->output( 'Running updateDatabaseFiles...' );
        $bucket = $ini->variable( "Rule-images", "Bucket" );
        $countfiles = 0;
        $countfiles_up = 0;
        $files = array();
        
        // Gettings images from DB and creating all aliases
        

        $db = eZDB::instance();
        $result = $db->ArrayQuery( "
                                    SELECT eco.id as co_id
                                    FROM ezcontentobject_attribute ecoa, ezcontentobject eco
                                    WHERE eco.id = ecoa.contentobject_id AND
                                          eco.current_version = ecoa.version AND
                                          ecoa.data_type_string = 'ezimage' AND
                                          eco.status = 1 AND
                                          ecoa.data_text != '' AND
                                          eco.modified >= " . $db_timestamp . "
                                    ORDER BY eco.modified
        " );
        if ( is_array( $result ) )
        {
            $cli->output( count( $result ) . " Object(s) available modified since " . $since->format( DateTime::ISO8601 ) );
            
            $imageINI = eZINI::instance( 'image.ini' );
            $aliases = $imageINI->variable( "AliasSettings", "AliasList" );
            // Limitation for debugging
            # $result = array( $result[0] );
            foreach ( $result as $object_item )
            {
                $obj = eZContentObject::fetch( $object_item["co_id"] );
                $obj_dm = $obj->dataMap();
                foreach ( $obj_dm as $obj_att )
                {
                    if ( $obj_att->attribute( "data_type_string" ) == "ezimage" )
                    {
                        $atts[] = $obj_att;
                        $image = new eZImageAliasHandler( $obj_att );
                        $imagepath = $image->aliasList();
                        foreach ( $aliases as $alias )
                        {
                            $image->imageAlias( $alias );
                        }
                        
                        $imagepath = $image->aliasList();
                        foreach ( $imagepath as $dir )
                        {
                            if ( array_key_exists( "url", $dir ) and $dir["url"] != "" )
                            {
                                $allfiles[] = array( 
                                    "bucket" => $bucket , 
                                    "file" => $dir["url"] 
                                );
                            }
                        }
                    }
                }
                eZContentObject::clearCache();
            }
        }
        $allfiles = array( 
            "buckets" => array( 
                $bucket 
            ) , 
            "files" => $allfiles , 
            "count" => count( $allfiles ) 
        );
        
        $countfiles = $allfiles["count"];
        
        // Uploading files
        #foreach ( array_unique( $allfiles["buckets"] ) as $bucketitem )
        #{
        #    $bucketfiles[$bucketitem] = $cdn->getAllDistributedFiles( $bucketitem );
        #}
        

        foreach ( $allfiles["files"] as $uploadfile )
        {
            
            $cli->output( "[UPLOAD] " . $uploadfile["bucket"] . "/" . str_replace( "\\", "/", $uploadfile["file"] ) );
            $countfiles_up ++;
            $file = eZClusterFileHandler::instance( str_replace( "\\", "/", $uploadfile["file"] ) );
            $file->fetch( true );
            $cdn->put( $file->filePath, $file->filePath, $uploadfile["bucket"] );
        }
        
        $cli->output( "$countfiles files checked total. \r\n" );
        $cli->output( "$countfiles_up files uploaded. \r\n" );
    }

    /**
     * Gets files from design/* extension/* var/cahe/public share/icons
     *
     * @param DateTime $since Defines the point of time from when the files should be updated
     * @throws Exception When an error occured
     */
    static function getDistributionFiles( DateTime $since = null )
    {
        $cli = eZCLI::instance();
        $ini = eZINI::instance( 'xrowcdn.ini' );
        $directories = $ini->variable( 'Settings', 'Directories' );
        $currrentDate = time();
        $countfiles = 0;
        $files = array();
        $filestoupload = array();
        $bucketlist = array();
        
        foreach ( $directories as $directory )
        {
            $fileSPLObjects = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ), RecursiveIteratorIterator::CHILD_FIRST );
            
            try
            {
                foreach ( $fileSPLObjects as $fullFileName => $fileSPLObject )
                {
                    if ( ! $fileSPLObject->isDir() )
                    {
                        $files[] = $fullFileName;
                    }
                }
            }
            catch ( UnexpectedValueException $e )
            {
                printf( "Directory [%s] contained a directory we can not recurse into", $directory );
            }
            
            if ( $ini->hasVariable( 'Rules', 'List' ) )
            {
                foreach ( $ini->variable( 'Rules', 'List' ) as $rule )
                {
                    $dirs = array();
                    $suffix = array();
                    
                    if ( $ini->hasSection( 'Rule-' . $rule ) )
                    {
                        // Check if rule is for distribution files
                        if ( $ini->hasVariable( 'Rule-' . $rule, 'Distribution' ) and $ini->variable( 'Rule-' . $rule, 'Distribution' ) == "true" )
                        {
                            if ( $ini->hasVariable( 'Rule-' . $rule, 'Dirs' ) and $ini->hasVariable( 'Rule-' . $rule, 'Suffixes' ) and $ini->hasVariable( 'Rule-' . $rule, 'Replacement' ) and $ini->hasVariable( 'Rule-' . $rule, 'Bucket' ) )
                            {
                                $bucket = $ini->variable( 'Rule-' . $rule, 'Bucket' );
                                $bucketlist[] = $ini->variable( 'Rule-' . $rule, 'Bucket' );
                                $dirs = $ini->variable( 'Rule-' . $rule, 'Dirs' );
                                $suffixes = $ini->variable( 'Rule-' . $rule, 'Suffixes' );
                                $dirs = '(' . implode( '|', $dirs ) . ')';
                                $suffixes = '(' . implode( '|', $suffixes ) . ')';
                                $rule = "/(" . $dirs . xrowCDNfilter::PATH_EXP . '\/' . xrowCDNfilter::BASENAME_EXP . '\.' . $suffixes . ')/imU';
                                foreach ( $files as $fileName )
                                {
                                    if ( preg_match( $rule, "/" . str_replace( '\\', '/', $fileName ) ) )
                                    {
                                        $filestoupload[] = array( 
                                            "bucket" => $bucket , 
                                            "file" => $fileName 
                                        );
                                        $countfiles ++;
                                    }
                                }
                            }
                        }
                    }
                } // foreach
            

            } // if has Rules List
        

        }
        return array( 
            "buckets" => $bucketlist , 
            "files" => $filestoupload , 
            "count" => $countfiles 
        );
    }

}
?>