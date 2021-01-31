<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Plugin_Name
 * @subpackage Plugin_Name/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 *
 */
class Hoteles_Public
{


	private $plugin_name;


	private $version;



	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_shortcode('hot-results-page', [$this, 'hot_results_page__function']);
		add_shortcode('hot-form-request', [$this, 'form_shortcode']);
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{



		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/hoteles-public.css', array(), $this->version, 'all');
	}


	public function enqueue_scripts()
	{


		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/hoteles-public.js', array('jquery'), $this->version, false);
	}


	// results angular shortcode
	public	function hot_results_page__function()
	{
		echo '<script>window.localStorage.setItem("apiWpUrl","' . home_url() . '")</script>';

		$urlBase = plugin_dir_url(__FILE__) . 'angularApp/src/index.php#' . $_GET['id'];
		$page = '<style>
			html,body{
				overflow: hidden
			}		
		</style><iframe id="hotelesApp" title="Filtro de Hoteles" frameBorder="0"
		src="' . $urlBase . '" >
	</iframe>';

		return $page;
	}


    /**
     * request xml
     * @param array $query_data
     */
    public function handle_post_request_xml($query_data = array())
    {
       $get =  wp_remote_get(XML_API .'?codigousu=QPKQ&clausu=xml343188&afiliacio=RS&secacc=136736&xml=<peticion>
                                            <tipo>110</tipo>
                                            <nombre>Servicio de disponibilidad por lista de hoteles</nombre>
                                            <agencia>Agencia Prueba</agencia>
                                            <parametros>
                                            <hotel>745388%23</hotel>
                                            <pais>MV</pais>
                                            <pais_cliente>ES</pais_cliente>
                                            <categoria>0</categoria>
                                            <fechaentrada>02/03/2021</fechaentrada>
                                            <fechasalida>02/10/2021</fechasalida>
                                            <afiliacion>RS</afiliacion>
                                            <usuario>XXXXXXXX</usuario>
                                            <numhab1>1</numhab1>
                                            <paxes1>2-0</paxes1>
                                            <edades1></edades1>
                                            <numhab2>0</numhab2>
                                            <paxes2>2-0</paxes2>
                                            <edades2></edades2>
                                            <numhab3>0</numhab3>
                                            <paxes3>2-0</paxes3>
                                            <edades3></edades3>
                                            <idioma>1</idioma>
                                            <informacion_hotel>0</informacion_hotel>
                                            <tarifas_reembolsables>0</tarifas_reembolsables>
                                            <comprimido>2</comprimido>
                                             <gastos>1</gastos>
                                            </parametros>
                                            </peticion>');


    }
    /**
     * request json
     */
	public	function handle_post_request()
	{

		if (isset($_POST['action']) && $_POST['action'] === 'hotels_form') {

			$checkIn = $_POST['entrada'];
			$checkOut = $_POST['salida'];
			$adultos = $_POST['adultos'];
			$ninos = $_POST['ninos'];
			$habitaciones = $_POST['habitaciones'];
			$data_query = array(
				"checkIn"=>$checkIn,
				"checkOut"=>$checkOut,
				"adultos"=>$adultos,
				"ninos"=>$ninos,
				"habitaciones"=>$habitaciones,

			);
			$result_array = new stdClass();

			$result_array->hotels = $this->get_hotels_filtered_request($data_query);
			$this->handle_post_request_xml($data_query);
	
			if (isset($result_array->hotels)) {
                $file = fopen("hotelesConsulta.txt", "w");




				$hotels_array_string =  serialize($result_array->hotels);
                fwrite($file, $hotels_array_string . PHP_EOL);

                fclose($file);
				$post_arr = array(
					'post_content' => $hotels_array_string,
					'post_type'    => 'hoteles',

				);

				$id_post = wp_insert_post($post_arr, true);


				wp_redirect(rtrim(get_permalink(get_page_by_title('Resultado Hoteles'))) . '?id="' . $id_post);
			}

			wp_send_json(false);
		}
	}


	// get hoteles
	public	function get_hotels_filtered_request($data_query)
	{


		$apiKey = API_KEY;
		$Secret = SECRET;
		$xsignature = hash("sha256", $apiKey . $Secret . time());
		$array_ids = [];

		$response = $this->getHotelsRooms($apiKey, $xsignature, $data_query);
		$response_decoded = json_decode($response);

		$final_array = new stdClass();
		foreach ($response_decoded->hotels->hotels as $key => $value) {

			array_push($array_ids, $value->code);
		}
		$reponse_details = json_decode($this->getHotels_details($apiKey, $xsignature, $array_ids));

		$final_array->hotels = $this->commbineArrays($response_decoded, $reponse_details);
		$final_array->checkDays = new stdClass();
		$final_array->checkDays->checkIn = $response_decoded->hotels->checkIn;
		$final_array->checkDays->checkOut = $response_decoded->hotels->checkOut;
		$final_array->checkDays->total = $response_decoded->hotels->total;
		// var_dump($final_array);
		// die();
		return $final_array;
	}






	//obtiene detalles de los hoteles
	public function getHotels_details($apiKey, $xsignature, $ids)
	{

		$ids_string = implode(',', $ids);
		// wp_send_json($ids_string);

		$url = 'https://api.test.hotelbeds.com/hotel-content-api/1.0/hotels?codes=' . $ids_string . '&language=CAS&fields=ranking,description,images,boardCodes,address';

		$getHotelsResponse  = wp_remote_get($url, array(
			'headers' => array(
				'Accept' => 'application/json',
				'Accept-Encoding' => 'application/gzip',
				'Content-Type' => 'application/json',
				'Api-key' => $apiKey,
				'X-Signature' => $xsignature
			),
			'timeout' => 8
		));
		// var_dump($getHotelsResponse);
		// die();
		return wp_remote_retrieve_body($getHotelsResponse);
	}


	//inserta detalles de hotel en primer array

	public function commbineArrays($arrayHotels, $arrayDetails)
	{
		$arrayTosend = array();


		foreach ($arrayHotels->hotels->hotels as $key => $hotel) {
			foreach ($arrayDetails->hotels as $key => $details) {
				if ($details->code == $hotel->code) {
					$hotel->description = $details->description;
					$hotel->address = $details->address;
					$hotel->ranking = $details->ranking;

					$hotel->boardCodes = $details->boardCodes;
					$hotel->images = $details->images[0];
					array_push($arrayTosend, $hotel);
				}
			}
		}

		return $arrayTosend;
	}


	//obtiene disponibilidad de habitaciones segun parametros
	public function getHotelsRooms($apiKey, $xsignature, $data_query)
	{


		$body = array(
			"geolocation" => array(
				"latitude" => 39.57119,
				"longitude" => 2.646633999999949,
				"radius" => 20,
				"unit" => "km"

			),
			"filter" => array(
				"maxHotels" => 70
			),

			"stay" => array(
				"checkIn" => $data_query['checkIn'],
				"checkOut" => $data_query['checkOut']
			),
			"occupancies" => array(
				array(
					"rooms" => $data_query['habitaciones'],
					"adults" => $data_query['adultos'],
					"children" => $data_query['ninos']
				)

			)

		);
		$responseHotelsRooms = wp_remote_post(
			'https://api.test.hotelbeds.com/hotel-api/1.0/hotels?language=CAS',
			array(
				'headers' => array(
					'Accept' => 'application/json',
					'Accept-Encoding' => 'application/gzip',
					'Content-Type' => 'application/json',
					'Api-key' => $apiKey,
					'X-Signature' => $xsignature
				),
				"body" => json_encode($body),
				'timeout' => 15
			)
		);

		if (is_array($responseHotelsRooms) && !is_wp_error($responseHotelsRooms)) {
			return wp_remote_retrieve_body($responseHotelsRooms);
		} else {
			wp_send_json(is_wp_error($responseHotelsRooms));
		}
	}


    public function form_shortcode()
    {

        // Things that you want to do.
        $checkIn = date("Y-m-d");
        $checkOut = date("Y-m-d", strtotime($checkIn . "+ 30 days"));

        $form = '
	 <form action="" id="searchHotels" method="POST" style="display: flex;justify-content: space-around;">
	 <input type="hidden" value="hotels_form" name="action">
	 	<div>
		 	<label class="formLabel">Destino</label><br>
			<input type="text" size="25" id="site_input" class="height_inputs">
			<br>
			<select id="select_input">
			</select>
		 </div>
	 	<div style="display: flex;margin-left: 1vw;">
			<div style="margin-right: -5vw;">
				<label class="formLabel">Entrada</label><br>
				<input type="date" class="height_inputs" name="entrada" id="checkIn"  min="' . $checkIn . '" required>
			</div>
			<div style="margin-left: 5vw;">
				<label class="formLabel">Salida</label><br>
				<input type="date" class="height_inputs" name="salida"  id="checkOut" min="' . $checkIn . '" max="' . $checkOut . '"  required>
			</div>
		 </div>
		 <div style="margin-left: 2vw; display: flex;">
			 <div style="margin-right: 5px;">
				<label class="formLabel">Adultos</label><br>
				<select id="adultos_select selects" name="adultos" class="height_inputs" required>
					<option value="0" selected>0</option>
					<option value="1">1</option>
					<option value="2">2</option>
					<option value="3">3</option>
					<option value="4">4</option>
					<option value="5">5</option>
					<option value="6">6</option>
					<option value="7">7</option>
					<option value="8">8</option>
					<option value="9">9</option>
				</select>
			 </div>
			 <div style="margin-right: 15px;">
			 	<label class="formLabel">Niños</label><br>
				<select id="ninos_select selects" name="ninos" class="height_inputs" required>
				<option value="0" selected>0</option>
				<option value="1">1</option>
				<option value="2" >2</option>
				<option value="3">3</option>
				<option value="4">4</option>
				<option value="5">5</option>
				<option value="6">6</option>
				<option value="7">7</option>
				<option value="8">8</option>
				<option value="9">9</option>
				</select>
			 </div>
			 <div>
			 	<label class="formLabel">Habitaciones</label><br>
				<select id="habitaciones_select selects" name="habitaciones" class="height_inputs" required>
					<option value="1">1</option>
					<option value="2" selected>2</option>
					<option value="3">3</option>
				</select>
			 </div>
		 </div>
		 <div style="display: flex;align-items: center;padding: 11px;">
		 	<button type="sumbmit" id="submit_hotels_form" style="padding: 9px;font-size: 15px;">BUSCAR</button>
		 </div>
	 </form>
	';
        return $form;
    }
}
