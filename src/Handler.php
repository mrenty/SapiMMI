<?php
namespace SapiStudio\AudiMMI;
use \SapiStudio\RestApi\AbstractHttpClient;

class Handler extends AbstractHttpClient
{
    const BASE_URL  = 'https://msg.audi.de/';
    const CAR_URL   = 'https://msg.audi.de/fs-car';
    const COMPANY   = 'Audi';
    const COUNTRY   = 'DE';
    
    const LOCATION_DATABASE   = 'vehicleLocation';
    
    protected $responseFormat = 'json';
    protected static $username;
    protected static $googleApiKey;
    protected static $password;
    private $authToken;
    private static $instance;
    private static $cacheHash;
    protected $primaryVin;
    protected $primaryCsid;
    
    
    /**
     * Handler::getVehicles()
     * 
     * @return
     */
    public function getVehicles(){
        return $this->get("/myaudi/carservice/v2/".self::COMPANY."/".self::COUNTRY."/vehicles");
    }
    
    /**
     * Handler::getVehicleData()
     * 
     * @return
     */
    public function getVehicleData(){
        return $this->get("/myaudi/carservice/v2/".self::COMPANY."/".self::COUNTRY."/vehicle/{csid}");
    }
    
    /**
     * Handler::getActions()
     * 
     * @return
     */
    public function getActions(){
        return $this->get('/bs/rlu/v1/".self::COMPANY."/".self::COUNTRY."/vehicles/{vin}/actions');
    }
    
    /**
     * Handler::loadPosition()
     * 
     * @return
     */
    public function loadPosition(){
        return $this->get("/bs/cf/v1/".self::COMPANY."/".self::COUNTRY."/vehicles/{vin}/position");
    }
    
    /**
     * Handler::vehicleMgmt()
     * 
     * @return
     */
    public function vehicleMgmt(){
        return $this->get("/vehicleMgmt/vehicledata/v2/".self::COMPANY."/".self::COUNTRY."/vehicles/{vin}");
    }
    
    /**
     * Handler::pairing()
     * 
     * @return
     */
    public function pairing(){
        return $this->get("/usermanagement/users/v1/".self::COMPANY."/".self::COUNTRY."/vehicles/{vin}/pairing");
    }
    
    /**
     * Handler::operations()
     * 
     * @return
     */
    public function operations(){
        return $this->get("/rolesrights/operationlist/v2/".self::COMPANY."/".self::COUNTRY."/vehicles/{vin}/operations");
    }
    
    /**
     * Handler::status()
     * 
     * @return
     */
    public function status(){
        return $this->get("/bs/vsr/v1/".self::COMPANY."/".self::COUNTRY."/vehicles/{vin}/status");
    }
    
    /**
     * Handler::pois()
     * 
     * @return
     */
    public function pois(){
        return $this->get(self::BASE_URL.'/audi/b2c/poinav/v1/vehicles/{vin}/pois');
    }
    
    /**
     * Handler::buildRequestUri()
     * 
     * @param mixed $baseUri
     * @param bool $path
     * @return
     */
    protected function buildRequestUri($baseUri,$path=false)
    {
        $url        = (substr($path, 0, 4) === 'http') ? $path : self::CAR_URL.$path;
        $url        = str_replace(['{csid}','{vin}'],[$this->getPrimaryCsid(),$this->getPrimaryVin()],$url);
        return $url;
    }
                
    /**
     * Handler::configure()
     * 
     * @return
     */
    public static function configure($options = [])
    {
        if (null === static::$instance)
            static::$instance = new static($options);
        return static::$instance;
    }
    
    /**
     * Handler::__construct()
     * 
     * @return
     */
    public function __construct($credentials){
        parent::__construct();
        $this->setOption('http_errors',false);
        $this->setHeaders([
            'Accept'        =>'application/json',
            'X-App-ID'      =>'de.audi.mmiapp',
            'X-App-Name'    =>'MMIconnect',
            'X-App-Version' =>'3.4.0',
            'X-Brand'       =>'audi',
            'X-Country-Id'  =>'DE',
            'X-Language-Id' =>'de',
            'X-Platform'    =>'google',
            'User-Agent'    =>'okhttp/3',
            'ADRUM_1'       =>'isModule:true',
            'ADRUM'         =>'isAray:true'
        ]);
        $this->setCredentials($credentials);
    }
    
    /**
     * Handler::login()
     * 
     * @return
     */
    public function login()
    {
        $response = $this->getHttpClient()->cacheRequest(self::$cacheHash)->post(self::CAR_URL.'/core/auth/v1/'.self::COMPANY.'/'.self::COUNTRY.'/token', [
                'verify'        => false,
                'form_params'   => [
                    'grant_type'    =>'password',
                    'username'      =>self::$username,
                    'password'      =>self::$password
                ],
            ]);
        $response = json_decode($response);
        if (!$response->access_token)
            throw new \Exception('Can not login using credentials: ' . sprintf('[Username: %s@%s].',self::$username,self::$password));
        return $response->access_token;
    }

    /**
     * Handler::setCredentials()
     * 
     * @return
     */
    public function setCredentials($credentials)
    {
        self::$username = !empty($credentials['username'])  ? $credentials['username']  : $check = true;
        self::$password = !empty($credentials['password'])  ? $credentials['password']  : $check = true;
        self::$googleApiKey = !empty($credentials['googleApiKey'])  ? $credentials['googleApiKey']  : $check = true;
        if ($check){
            throw new \Exception('Require in array [username], [password], [googleApiKey]');
        }
        self::$cacheHash = md5(realpath(dirname(__FILE__)).self::$username.self::$password);
        $this->addHeader('Authorization','AudiAuth 1 '.$this->login());
        $vehicles           = $this->getVehicles();
        if($vehicles){
            $this->setPrimaryVin($vehicles['getUserVINsResponse']->CSIDVins[0]->VIN);
            $this->setPrimaryCsid($vehicles['getUserVINsResponse']->CSIDVins[0]->CSID);
        }
    }
    
    /**
     * Handler::setPrimaryVin()
     * 
     * @return void
     */
    public function setPrimaryVin($vin = null){
        $this->primaryVin = $vin;
    }
    
    /**
     * Handler::getPrimaryVin()
     * 
     * @return
     */
    public function getPrimaryVin(){
        return $this->primaryVin;
    }
    
    /**
     * Handler::setPrimaryCsid()
     * 
     * @return void
     */
    public function setPrimaryCsid($csid = null){
        $this->primaryCsid = $csid;
    }
    
    /**
     * Handler::getPrimaryCsid()
     * 
     * @return
     */
    public function getPrimaryCsid(){
        return $this->primaryCsid;
    }
    
    /**
     * Handler::getCredential()
     * 
     * @return
     */
    public function getCredential($credentialName = null){
        return (!self::$$credentialName) ? false : self::$$credentialName;
    }
}