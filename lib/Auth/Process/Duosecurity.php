<?php
/**
 * Duo Security Authentication Processing filter
 *
 * Filter to present Duo two factor authentication form
 *
 * @package simpleSAMLphp
 */
class sspmod_duosecurity_Auth_Process_Duosecurity extends SimpleSAML_Auth_ProcessingFilter
{

    /**
     * Include attribute values
     *
     * @var bool
     */
    private $_includeValues = false;

    private $_duoComplete = null;

    private $_akey;

    private $_ikey;

    private $_skey;

    private $_host;

    private $_authSources = "all";
    /**
     * Initialize Duo Security 
     *
     * Validates and parses the configuration
     *
     * @param array $config   Configuration information
     */
    public function __construct($config, $reserved)
    {
	$this->_host = $config['host'];
	$this->_akey = $config['akey'];
	$this->_ikey = $config['ikey'];
	$this->_skey = $config['skey'];
	$this->_authSources = $config['authSources'];

    }

    /**
     * Helper function to check whether Duo is disabled.
     *
     * @param mixed $option  The consent.disable option. Either an array or a boolean.
     * @param string $entityIdD  The entityID of the SP/IdP.
     * @return boolean  TRUE if disabled, FALSE if not.
     */
    private static function checkDisable($option, $entityId) {
        if (is_array($option)) {
            return in_array($entityId, $option, TRUE);
        } else {
            return (boolean)$option;
        }
    }

    /**
     * Process a authentication response
     *
     * This function saves the state, and redirects the user to the page where
     * the user can log in with their second factor.
     *
     * @param array &$state The state of the response.
     *
     * @return void
     */
    public function process(&$state)
    {
        assert('is_array($state)');
        assert('array_key_exists("UserID", $state)');
        assert('array_key_exists("Destination", $state)');
        assert('array_key_exists("entityid", $state["Destination"])');
        assert('array_key_exists("metadata-set", $state["Destination"])');		
        assert('array_key_exists("entityid", $state["Source"])');
        assert('array_key_exists("metadata-set", $state["Source"])');

        $spEntityId = $state['Destination']['entityid'];
        $idpEntityId = $state['Source']['entityid'];

        $metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();

        /**
         * If the Duo Security module is active on a bridge $state['saml:sp:IdP']
         * will contain an entry id for the remote IdP. If not, then
         * it is active on a local IdP and nothing needs to be
         * done.
         */
        if (isset($state['saml:sp:IdP'])) {
            $idpEntityId = $state['saml:sp:IdP'];
            $idpmeta         = $metadata->getMetaData($idpEntityId, 'saml20-idp-remote');
            $state['Source'] = $idpmeta;
        }

	if (isset($state['duo_complete'])) {
		return;
	}

	// Set Keys for Duo SDK
	$state['duosecurity:akey'] = $this->_akey;
	$state['duosecurity:ikey'] = $this->_ikey;
	$state['duosecurity:skey'] = $this->_skey;
	$state['duosecurity:host'] = $this->_host;
	$state['duosecurity:authSources'] = $this->_authSources;

        // User interaction nessesary. Throw exception on isPassive request	
        if (isset($state['isPassive']) && $state['isPassive'] == true) {
            throw new SimpleSAML_Error_NoPassive(
                'Unable to login with passive request.'
            );
        }

        // Save state and redirect
	$id  = SimpleSAML_Auth_State::saveState($state, 'duosecurity:request');
        $url = SimpleSAML_Module::getModuleURL('duosecurity/getduo.php');
        SimpleSAML_Utilities::redirectTrustedURL($url, array('StateId' => $id));
    }

}
