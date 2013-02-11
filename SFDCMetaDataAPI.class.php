<?php

    ini_set("soap.wsdl_cache_enabled", 0);

    class SFDCMetaDataAPI {



        public $username;
        public $password;
        public $token;


        private $_client;

        private $_sessionId;
    

    public function __construct($username,$password,$token = null) {


        $this->__setUsername($username);
        $this->__setPassword($password);
        $this->__setToken($token);

        $this->_client = new SoapClient(
                                'partner.wsdl.xml',
                             array(
                                    'trace' => true,
                                    'exceptions' => true
                                )
                            );



    }


    protected function __setUsername($username) {

        if ($username) {

            $this->username = $username;

            return true;

        } else {

            return false;

        }

    }

    protected function __setPassword($password) {

        if ($password) {

            $this->password = $password;
            
            return true;
        
        } else {

            return false;
        }

    }


    protected function __setToken($token) {

        if ($token) {

            $this->token = $token;

            return true;

        } else {

            return false;
        
        }
    
    }


    public function login() {

            $this->_client->login( 
                    array(
                            'username' => $this->username,
                            'password' => $this->password,
                        )
                );

            $soap_response = $this->_client->__getLastResponse();



            $loginData = $this->_setupXML($soap_response);
    
            $this->_sessionId = $loginData->xpath('//sf:sessionId')[0];

    


            $this->_client = new SoapClient(
                                        'metadata.wsdl.xml',
                                        array(
                                                'trace' => true,
                                                 'exceptions' => true,
                                                 'sessionId' => $this->_sessionId
                                            )
                                    );



            $sessionVar = array(
                                 'sessionId' => new SoapVar(
                                                            $this->_sessionId,
                                                             XSD_STRING)
                            );

            $headerBody = new SoapVar(
                                    $sessionVar,
                                     SOAP_ENC_OBJECT);

            $session_header = new SoapHeader(
                                                'http://soap.sforce.com/2006/04/metadata',
                                                'SessionHeader',
                                                $headerBody,
                                                false
                                            );

            $header_array = array (
                                    $session_header
                                );

            $this->_client->__setSoapHeaders($header_array);



    }


    protected function _setupXML($soap_response) {



            $xml = simplexml_load_string($soap_response);
    
            $xml->registerXPathNamespace(
                                            'sf',
                                            'urn:partner.soap.sforce.com'
                                        );

            return $xml;



    }


    protected function _setupXMLNS($soap_response) {


            $xml = simplexml_load_string($soap_response);
    
            $xml->registerXPathNamespace(
                                            'sf',
                                            'http://soap.sforce.com/2006/04/metadata'
                                        );

            return $xml;        
    }

    public function pullAll() {


        $dataArray = array();
        $queryArray = array();


        $this->_client->describeMetadata(27);



        $soap_response = $this->_client->__getLastResponse();

        $xml = $this->_setupXMLNS($soap_response);


                foreach( $xml->xpath('//sf:metadataObjects') as $item) {


                        $xmlName = (string) $item->xmlName;

                        $queryArray[] = array('type' => $xmlName);


                }

                $queryArray[] = array('type' => 'ReportFolder');




        foreach( array_chunk($queryArray, 3) as $qArray) {

                try {

                    $this->_client->listMetaData(
                    						array(
                    								'asOfVersion' => 27.0,
                    								 'queries' => $qArray
                    							)
                    					);

                    $soap_response =  $this->_client->__getLastResponse();

                    $xml = $this->_setupXMLNS($soap_response);

                   	foreach( $xml->xpath('//sf:fileName') as $item) {

                		$item = (string) $item;

                		$dataArray[$item] = $item;


                	}

                } catch (Exception $e) { 

                    echo 'Exception = ' . $e->getMessage() . "\r\n";
            }

        }

       $this->_client->retrieve( 
                            array(
                                    'retrieveRequest' => 
                                                        array(
                                                                'specificFiles' => array_keys($dataArray),
                                                                'apiVersion' => 27,
                                                                'singlePackage' => true,
                                                                'packageNames' => null,
                                                                'unpackaged' => true
                                                            )
                                    )
                        ); 




    	$soap_response = $this->_client->__getLastResponse();

        $xml = $this->_setupXMLNS($soap_response);


    	foreach($xml->xpath('//sf:id') as $item) {

    		$Id = $item;

    	}


        foreach(range(1,100) as $time) {

            $this->_client->checkStatus( 
                                            array(
                                                    'asyncProcessId' =>  $Id
                                                )
                                        );

            $soap_response = $this->_client->__getLastResponse();
 
            $xml = $this->_setupXMLNS($soap_response);

            $done = (string) $xml->xpath('//sf:done')[0];

            if ($done == 'false') {

                echo 'Polling...'  . PHP_EOL;

                sleep(1);

            } else {

                echo 'Done!' . PHP_EOL;

                break;

            }

        }

            $this->_client->checkRetrieveStatus( 
                                            array(
                                                    'asyncProcessId' =>  $Id
                                                )
                                        );


    	$soap_response = $this->_client->__getLastResponse();


        $xml = $this->_setupXMLNS($soap_response);

        $zipFile = base64_decode($xml->xpath('//sf:zipFile')[0]);

    		if ( !@file_put_contents('package.zip', $zipFile) ) {

                echo "Couldn't create file package.zip: $old_track\r\n";
            }

    }

    public function deploy() {


            $zip = new ZipArchive();
            $filename = "package.zip";

            if ($zip->open($filename, ZIPARCHIVE::CREATE)!==TRUE) {
            
                die('here');            
            }
            
            $zip->addFromString('pages/WatchListReport.page' , file_get_contents('/home/jimi/workspace/ConversionOrg-Sandbox/src/classes/WatchListReport.cls'));
            $zip->addFromString('pages/WatchListReport.page-meta.xml' , file_get_contents('/home/jimi/workspace/ConversionOrg-Sandbox/src/classes/WatchListReport.cls-meta.xml'));

            $zip->addFromString('package.xml', '<?xml version="1.0" encoding="UTF-8"?>
                                                <Package xmlns="http://soap.sforce.com/2006/04/metadata">
                                                    <types>
                                                        <members>WatchListReport</members>
                                                        <name>ApexPage</name>
                                                    </types>
                                                        <version>27.0</version>
                                                </Package>'
                                );


            $zip->close();


            $zip64 = base64_encode( file_get_contents('package.zip') ) ;


            
            $this->_client->deploy( 
                                    array(
                                            'ZipFile' => $zip64,
                                            'DeployOptions' => array(
                                                                        'checkOnly' => false,
                                                                        'allowMissingFiles' => false,
                                                                        'autoUpdatePackage' => false,
                                                                        'ignoreWarnings' => false,
                                                                        'performRetrieve' => false,
                                                                        'purgeOnDelete' => false,
                                                                        'rollbackOnError' => false,
                                                                        'runAllTests' => false,
                                                                        'singlePackage' => false,


                                                                    ),
                                         )
                                    );

            echo $this->_client->__getLastResponse();

        $soap_response = $this->_client->__getLastResponse();

        $xml = $this->_setupXMLNS($soap_response);


        foreach($xml->xpath('//sf:id') as $item) {

            $Id = $item;

        }


        foreach(range(1,100) as $time) {

            $this->_client->checkStatus( 
                                            array(
                                                    'asyncProcessId' =>  $Id
                                                )
                                        );

            $soap_response = $this->_client->__getLastResponse();
 
            $xml = $this->_setupXMLNS($soap_response);

            $done = (string) $xml->xpath('//sf:done')[0];

            if ($done == 'false') {

                echo 'Polling...'  . PHP_EOL;

                sleep(10);

            } else {

                echo 'Done!' . PHP_EOL;

                break;

            }
        }

                    $this->_client->checkDeployStatus( 
                                            array(
                                                    'asyncProcessId' =>  $Id
                                                )
                                        );


        $soap_response = $this->_client->__getLastResponse();

        echo 'Response = ' . $soap_response . "\r\n";

    }



    public function testCreate() {

        $obj = new stdClass();
        $obj->fullName = 'MyTestObject__c';
        $obj->deploymentStatus = 'Deployed';
        $obj->description = 'My Custom Object';
        $obj->label = 'My Custom Object';
        $obj->pluralLabel = 'My Custom Objects';
        $obj->sharingModel = 'ReadWrite';

        $nameFieldObj = new stdClass();

        $nameFieldObj->fullName = 'Name';
        $nameFieldObj->type ='AutoNumber';
        $nameFieldObj->label = 'Name';
        $nameFieldObj->deploymentStatus = 'Deployed';

        $obj->nameField = $nameFieldObj;    



        try {

                $this->_client->create( 
                                        array(
                                                'metadata' =>  
                                                               new SoapVar(
                                                                            $obj,
                                                                            SOAP_ENC_OBJECT,
                                                                            'CustomObject',
                                                                            'http://soap.sforce.com/2006/04/metadata'
                                                                            )
                                                 ) 
                                    );

        } catch (Exception $e) {

                echo 'Exception = ' . $e->getMessage();

        }


    }
}