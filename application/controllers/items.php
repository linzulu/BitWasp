<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Items Controller
 *
 * This class handles with requests for items.
 * 
 * @package		BitWasp
 * @subpackage	Controllers
 * @category	Items
 * @author		BitWasp
 * 
 */
class Items extends CI_Controller {

	/**
	 * Constructor
	 *
	 * @access	public
	 * @see		Models/Items_Model
	 */
	public function __construct() {
		parent::__construct();
		$this->load->model('items_model');
	}
	
	/**
	 * Load all items.
	 * NOTE: pagination to come soon.
	 * URI: /items
	 * 
	 * @access	public
	 * @see		Models/Items_Model
	 */
	public function index($page = 0) {
		$data['title'] = 'Items';
		$data['page'] = 'items/index';
		
		$info = (array)json_decode($this->session->flashdata('returnMessage'));
		if(count($info) !== 0)
			$data['returnMessage'] = $info['message'];		
		
		$items_config = array();
		$items_per_page = 4;
		$data['links'] = $this->items_model->pagination_links($items_config, site_url('items'), 2);
		$data['items'] = $this->items_model->get_list_pages($items_config, $page);
		
		$this->load->library('Layout', $data);
	}
	
	/**
	 * Load all items in a category.
	 * URI: /category/$hash
	 * 
	 * @access	public
	 * @see		Models/Items_Model
	 * @see		Models/Categories_Model
	 * 
	 * @param	string
	 * @return	void
	 */
	public function category($hash, $page = 0) {
		$this->load->model('categories_model');
		$data['category'] = $this->categories_model->get(array('hash' => "$hash"));
		if($data['category'] == FALSE)
			redirect('items');

					
		$data['title'] = 'Items by Category: '.$data['category']['name'];
		$data['custom_title'] = 'Category: '.$data['category']['name'];
		$data['page'] = 'items/index';
		
		$items_per_page = 4;
		$items_config = array('category' => $data['category']['id']);
		$data['links'] = $this->items_model->pagination_links($items_config, site_url("category/$hash"), 3);
		$data['items'] = $this->items_model->get_list_pages( array('category' => $data['category']['id']), $page );
		
		$this->load->library('Layout', $data);
	}

	/**
	 * Location
	 * 
	 * This function requires that $source is either 'ship-to' or 'ship-from'.
	 * If not, it will redirect the user to the items page. It will then 
	 * check if a form was posted, and will then load the appropriate items,
	 * or if the requested post $location doesn't exist, will redirect to items.
	 * Otherwise, it can be called by supplying the parameters via GET.
	 * URI: /location/$source/$location
	 * 
	 * @access	public
	 * @see		Models/Items_Model
	 * @see		Models/Categories_Model
	 * 
	 * @param	string	$source(optional)
	 * @param	string	$location 
	 * @return	void
	 */
	public function location($source = '', $location = NULL, $page = 0) {
		// If the $source is invalid, redirect to the items page.
		if(!$this->general->matches_any($source, array('ship-to','ship-from')))
			redirect('items');

		$this->load->model('location_model');
		$this->load->model('shipping_costs_model');

		// Load any posted location information.
		if($this->input->post('ship_to_submit') == 'Go') {
			$location = $this->input->post('location');
			$data['location_name'] = $this->location_model->location_by_id($location);
			if($data['location_name'] == FALSE)
				redirect('items');
			$items_config = array('item_id_list' => $this->shipping_costs_model->list_IDs_by_location($location));
		} else if($this->input->post('ship_from_submit') == 'Go') {
			$location = $this->input->post('location');
			$data['location_name'] = $this->location_model->location_by_id($location);
			if($data['location_name'] == FALSE)
				redirect('items');
			$items_config = array('ship_from' => $location);

		} else {
			$location_info = $this->location_model->get_location_info($location);
			if($location_info == FALSE)
				redirect('items');
			$data['location_name'] = $location_info['location'];

			if($source == 'ship-to') {
				// Load the id's of items which are available in the $location
				$items_config = array('item_id_list' => $this->shipping_costs_model->list_IDs_by_location($location));
			} else if($source == 'ship-from') {
				// Simply specify the item has ship_from=$location.
				$items_config = array('ship_from' => $location);
			}
		}

		$data['links'] = $this->items_model->pagination_links($items_config, site_url("location/{$source}/{$location}"), 4);
		$data['items'] = $this->items_model->get_list_pages($items_config, $page);	
		
		// Set the appropriate titles.
		if($source == 'ship-from') {
			$data['title'] = 'Items shipped from '.$data['location_name'];
			$data['custom_title'] = 'Shipping From: '.$data['location_name'];
		} else if($source == 'ship-to') {
			$data['title'] = 'Items shipped to '.$data['location_name'];
			$data['custom_title'] = 'Shipping To: '.$data['location_name'];
		}
		
		$data['page'] = 'items/index';
		$this->load->library('Layout', $data);
	}


	/**
	 * Load a specific item
	 * URI: /item/$hash
	 * 
	 * @access	public
	 * @see		Models/Items_Model
	 * 
	 * @param	string	$hash
	 * @return	void
	 */	
	public function get($hash) {
		$data['item'] = $this->items_model->get($hash);
		if($data['item'] == FALSE) 
			redirect('items');
			
		$info = (array)json_decode($this->session->flashdata('returnMessage'));
		if(count($info) !== 0)
			$data['returnMessage'] = $info['message'];

		$data['logged_in'] = $this->current_user->logged_in();
		$data['page'] = 'items/get';
		$data['title'] = $data['item']['name'];
		$data['user_role'] = $this->current_user->user_role;
		$data['browsing_currency'] = $this->current_user->currency;
		
		$this->load->model('shipping_costs_model');
		$data['shipping_costs'] = $this->shipping_costs_model->for_item($data['item']['id']);

		$this->load->model('review_model');
		$data['reviews'] = $this->review_model->random_latest_reviews(8, 'item', $hash);
		$data['review_count']['all'] = $this->review_model->count_reviews('item', $hash);
		$data['review_count']['positive'] = $this->review_model->count_reviews('item', $hash, 0);
		$data['review_count']['disputed'] = $this->review_model->count_reviews('item', $hash, 1);
		$data['average'] = $this->review_model->current_rating('item', $hash);
		
		if($data['browsing_currency']['id'] !== '0' && $data['shipping_costs'] !== FALSE){
			$this->load->model('currencies_model');
			$currency = $this->currencies_model->get($data['browsing_currency']['id']);
			foreach($data['shipping_costs'] as &$cost) {
				$cost['cost'] = round($cost['cost']*$currency['rate'], 3, PHP_ROUND_HALF_UP);
			}
		}
		$this->load->library('Layout', $data);
	}
};

/* End of File: Items.php */
