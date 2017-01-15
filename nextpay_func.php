<?php
include_once('func_shortcode.php');


add_action('admin_enqueue_scripts', 'my_style_nextpay');
function my_style_nextpay() {
	$dir = plugin_dir_url(__FILE__).'style.css';
	wp_register_style( 'custom_nextpay_css',$dir);
    wp_enqueue_style( 'custom_nextpay_css' );
}
add_action('wp_login', 'when_login',10, 2);
function when_login($user_login, $user) 
{
	global $wpdb;	
	
	date_default_timezone_set("Asia/Tehran");
	$_Today=get_option('vip_today_time');
	$_Date=date("Y-m-d");
	
	$diff = abs(strtotime($_Date)-strtotime($_Today));
	$diff = $diff/(60*60*24);
	
	if($diff>=1)
	{
		update_option('vip_today_time',$_Date);
		$current_user=wp_get_current_user();
		$user_id=$current_user->ID;
		$Tuser = $wpdb->prefix . "users";
		$Tusermeta = $wpdb->prefix . "usermeta";
		$users = $wpdb->get_row("SELECT * FROM $Tuser" , ARRAY_A);
		foreach($users as $user)
		{
			$x=get_user_meta($user['ID'],'exp_per_day',true);
			if($x)
			{	
				update_user_meta($user['ID'],'extant_daily',$x);					
			}
		}
	}
}

nextpayFileDownload::init();
class nextpayFileDownload {
	protected static $currencies = array(
		'USD' => array('United States Dollar','$'),
		'AUD' => array('Australian Dollar','AUD$'),
		'BRL' => array('Brazilian Real','R$'),
		'GBP' => array('British Pound','&pound;'),
		'CAD' => array('Canadian Dollar','CAD$'),
		'CNY' => array('Chinese Yuan','&#20803;'),
		'DKK' => array('Danish Krone','kr.'),
		'EUR' => array('European Euro','&#8364;'),
		'HKD' => array('Hong Kong Dollar','HK$'),
		'HUF' => array('Hungarian Forint','Ft'),
		'INR' => array('Indian Rupee','INR'),
		'IDR' => array('Indonesian Rupiah','Rp'),
		'JPY' => array('Japanese Yen','&yen;'),
		'MXN' => array('Mexican Peso','MEX$'),
		'NZD' => array('New Zealand Dollar','NZ$'),
		'NOK' => array('Norwegian Kroner','kr'),
		'PLN' => array('Polish Zloty','zl.'),
		'RUB' => array('Russian Ruble','RUB'),
		'SAR' => array('Saudi Riyal','SR'),
		'SGD' => array('Singapore Dollar','SGD$'),
		'ZAR' => array('South African Rand','R'),
		'SEK' => array('Swedish Krona','kr'),
		'CHF' => array('Swiss Franc','CHF'),
		'THB' => array('Thai Bhat','&#3647;'),
		'TRY' => array('Turkish Lira','TRY'),
		'TWD' => array('Taiwan Dollar','TWD')
	);
	const VERSION = '1.3';
	const DB_VERSION = "1.0";
	public static function init() {
		
		$dir = plugin_dir_path(__FILE__);	
		register_activation_hook($dir.'nextpay_file_download.php', array(__CLASS__, 'install'));
//		register_activation_hook(__FILE__, array(__CLASS__, 'install'));
		// admin stuff
		add_action('admin_menu', array(__CLASS__, 'admin_menu'));
		add_action('admin_init', array(__CLASS__, 'admin_init'));
		// media buttons hook
		add_action('media_buttons_context', array(__CLASS__, 'media_button'));
		add_action('media_buttons_context', array(__CLASS__, 'vip_media_button'));
		add_action('media_buttons_context', array(__CLASS__, 'vip_data_button'));
		
		// insert form
		add_action('admin_footer', array(__CLASS__, 'add_pfd_form'));
		add_action('admin_footer', array(__CLASS__, 'add_linkdownload_vip_form'));
		add_action('admin_footer', array(__CLASS__, 'add_vip_data_form'));
		
		// listener for ipn activation
		add_action('template_redirect', array(__CLASS__, 'var_listener'));
		add_action('template_redirect', array(__CLASS__, 'vip_listener'));
		
		
		add_filter('query_vars', array(__CLASS__, 'register_vars'));
		//add_action('admin_menu', array(__CLASS__, 'add_meta_box'));
		
	}
	
	
	
	
	protected static function transactioncode($length = "") {
		$code = md5(uniqid(rand(), true));
		if ($length != "") return strtoupper(substr($code, 0, $length));
		else return strtoupper($code);
	}
	protected static function relative_time($ptime) {
		date_default_timezone_set("Asia/Tehran");
		$etime = time() - $ptime;
		if ($etime < 1) {
			return '0 seconds';
		}
		$a = array( 12 * 30 * 24 * 60 * 60  =>  'year',
					30 * 24 * 60 * 60       =>  'month',
					24 * 60 * 60            =>  'day',
					60 * 60                 =>  'hour',
					60                      =>  'minute',
					1                       =>  'second'
					);
		foreach ($a as $secs => $str) {
			$d = $etime / $secs;
			if ($d >= 1) {
				$r = round($d);
				return $r . ' ' . $str . ($r > 1 ? 's' : '');
			}
		}
	}
	
	
	
	
	public static function install() {
		global $wpdb;
		$message_default = <<<EOT
بابت خريد محصول [PRODUCT_NAME] تشکر مي کنيم! لينک دانلود در انتهاي  اين پيغام قرار گرفته. براي پيگيري هاي بعدي شماره تراکنش [TRANSACTION_ID] را يادداشت نماييد.
EOT;
		$message_default_nofile = <<<EOT
بابت خريد محصول [PRODUCT_NAME] تشکر مي کنيم! لينک دانلود در انتهاي  اين پيغام قرار گرفته. براي پيگيري هاي بعدي شماره تراکنش [TRANSACTION_ID] را يادداشت نماييد.
EOT;


		
		
		add_option("vip_message_email",'بابت خريد اشتراک [ACCOUNT_NAME] تشکر مي کنيم! براي پيگيري هاي بعدي شماره تراکنش [TRANSACTION_ID] را يادداشت نماييد.
' , '','yes');
		add_option("vip_message_novip","لینک دانلود برای کاربران vip می باشد. ثبت نام کنید و اشتراک vip بخرید",'','yes');
		
		add_option("vip_nextpay_api", "YOUR-API", '','yes');
		
		add_option("vip_nextpay_return_url", get_option("siteurl"), '','yes');
		date_default_timezone_set("Asia/Tehran");
		$inDate=date("Y-m-d");
		add_option("vip_today_time",$inDate);
		
		$table_name = $wpdb->prefix . "vip_accounts";
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE TABLE " . $table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
				descript VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
				cost bigint(11) NOT NULL,
				day int NOT NULL,
				per_day int NOT NULL,
				PRIMARY KEY id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
		
				
		
		$table_name = $wpdb->prefix . "vip_orders";
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE TABLE " . $table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				idaccount mediumint(9) NOT NULL,
				iduser bigint(20) unsigned NOT NULL,
				order_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
				fulfilled mediumint(9) NOT NULL,
				cost bigint(11) NOT NULL,
				created_at bigint(11) DEFAULT '0' NOT NULL,
				PRIMARY KEY id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
		
		
		
		
		add_option("email_message", $message_default, '','yes');
		add_option("email_message_nofile", $message_default_nofile, '','yes');
		add_option("expire_links_after", 7, '','yes');
		
		add_option("nextpay_direct", 0, '','yes');
		add_option("nextpay_return_url", get_option("siteurl"), '','yes');
		$table_name = $wpdb->prefix . "pfd_products";
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE TABLE " . $table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
				file VARCHAR(255) NOT NULL,
				downloads bigint(11) NOT NULL,
				cost bigint(11) NOT NULL,
				created_at bigint(11) DEFAULT '0' NOT NULL,
				PRIMARY KEY id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
		$table_name = $wpdb->prefix . "pfd_orders";
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE TABLE " . $table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				product_id mediumint(9) NOT NULL,
				order_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
				fulfilled mediumint(9) NOT NULL,
				cost bigint(11) NOT NULL,
				created_at bigint(11) DEFAULT '0' NOT NULL,
				PRIMARY KEY id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
		$table_name = $wpdb->prefix . "pfd_transactions";
		if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
			$sql = "CREATE TABLE " . $table_name . " (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				product_id mediumint(9) NOT NULL,
				order_id mediumint(9) NOT NULL,
				order_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
				protection_eligibility VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payer_id VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				tax bigint(11) NULL,
				payment_date VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payment_status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				first_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				last_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payer_status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				business VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_street VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_city VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_state VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_zip VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_country_code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				address_country VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				quantity VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				verify_sign VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payer_email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				txn_id VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payment_type VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				receiver_email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				receiver_id VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
				txn_type VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				item_name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				mc_currency VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				item_number VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				residence_country VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				custom VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				receipt_id VARCHAR(255)  NULL,
				transaction_subject VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NULL,
				payment_fee bigint(11) NOT NULL,
				payment_gross bigint(11) NOT NULL,
				created_at bigint(11) DEFAULT '0' NOT NULL,
				PRIMARY KEY id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
		
		
		$table_name = $wpdb->prefix . "pfd_transactions";
		$myTransactions = $wpdb->get_row("SELECT * FROM $table_name limit 1");
		if(!isset($myTransactions->mobile)){
			$wpdb->query("ALTER TABLE $table_name ADD mobile VARCHAR(255) NULL DEFAULT ' '");
		}
		
		add_option("pfd_db_version", self::DB_VERSION);
	}
	protected static function get_currency() {
		if (get_option('pfd_currency')) {
			$cc = get_option('pfd_currency');
		} else {
			$cc = "USD";
		}
		return $cc;
	}
	protected static function get_currency_symbol() {
		$cc = self::get_currency();
		return self::$currencies[$cc][1];
	}
	public static function validate_currency($currency) {
		if (!empty(self::$currencies[$currency]))
			return $currency;
		return 'USD';
	}
	public static function admin_init() {
		register_setting('vip_options', 'vip_message_email');
		register_setting('vip_options', 'vip_message_novip');
		register_setting('vip_options', 'vip_nextpay_api');
		register_setting('vip_options', 'vip_nextpay_return_url');
		
		register_setting('pfd_options', 'email_message');
		register_setting('pfd_options', 'email_message_nofile');
		register_setting('pfd_options', 'expire_links_after', 'intval');
		
		register_setting('pfd_options', 'nextpay_direct', 'intval');
		register_setting('pfd_options', 'nextpay_return_url');
		register_setting('pfd_options', 'pfd_currency', array(__CLASS__, 'validate_currency'));
	}
	public static function admin_menu() {
		add_menu_page( "", "محصولات فروشگاه", 'manage_options', 'nextpay-file-download-products', array(__CLASS__, 'admin_products_router'),plugins_url("images/basket.png",__FILE__));
		add_submenu_page( 'nextpay-file-download-products', "فروش ها", "فروش ها", 'manage_options', "nextpay-file-download-transactions", array(__CLASS__,'admin_transactions'));
		add_submenu_page( 'nextpay-file-download-products', "تنظیمات", "تنظيمات", 'manage_options', "nextpay-file-download-settings", array(__CLASS__, 'admin_settings'));
		add_submenu_page( 'nextpay-file-download-products', "راهنما", "راهنما", 'manage_options', "nextpay-file-download-help", array(__CLASS__, 'admin_help'));

		add_menu_page( "", "اشتراک VIP", 'manage_options', 'nextpay-vip-accounts', array(__CLASS__, 'admin_vip_router'), plugins_url("images/vip.png",__FILE__));
		add_submenu_page( 'nextpay-vip-accounts', "تنظیمات", "تنظيمات", 'manage_options', "nextpay-vip-settings", array(__CLASS__, 'admin_settingsvip'));
		add_submenu_page( 'nextpay-vip-accounts', "کاربران VIP", "کاربران VIP", 'manage_options', "nextpay-vip-orders", array(__CLASS__,'admin_orders'));
		
		
	}
	public static function admin_products_router() {
		$action = '';
		if (!empty($_REQUEST['action'])) {
			$action = $_REQUEST['action'];
		}
		switch ($action) {
			case 'edit':
				return self::admin_products_edit();
				break;
			case 'delete':
				return self::admin_products_delete();
				break;
			case 'add':
				return self::admin_products_add();
				break;
			default:
				return self::admin_products();
		}
	}
	protected static function admin_products_edit() {
		global $wpdb;
		$table_name = $wpdb->prefix . "pfd_products";
		
		if (isset($_POST["product_name"])) {
			$name = $_POST["product_name"];
			$url = $_POST["product_url"];
			$cost = $_POST["product_cost"];
			$wpdb->update( $table_name, array('name' => $name, 'file' => $url, 'cost' => $cost), array('id' => $_GET["id"]), array( '%s', '%s', '%s'));
		}
		
		$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$_GET["id"]) , ARRAY_A, 0);
	?>
    
    
    <?php 
	echo'<div class="wrap">
		<h2>ويرايش محصول: '.$product['name'].'</h2>
		<a href="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-file-download-products">&laquo; بازگشت به صفحه محصولات</a>
		<form action="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-file-download-products&action=edit&id='.$_GET['id'].'" method="post">
		<table class="form-table">
			<tr valign="top">
				<th scope="row">نام محصول</th>
				<td><input type="text" name="product_name" style="width:250px;" value="'.str_replace('"','\"',$product["name"]).'" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">لينک محصول</th>
				<td><input type="text" name="product_url" style="width:400px;" value="'.str_replace('"','\"',$product["file"]).'" /><br />(لطفا اطمينان حاصل کنيد که اين لينک مخفي است<br />اين لينک پس از خريد موفق به خريدار نشان داده مي شود )</td>
			</tr>
			<tr valign="top">
				<th scope="row">قيمت محصول(به ازاي هر بار دانلود)</th>
				<td><input type="text" name="product_cost" style="width:150px;" value="'.str_replace('"','\"',$product["cost"]).'" />ريال</td>
			</tr>
			<tr valign="top">
				<th scope="row">&nbsp;</th>
				<td>
					<input type="submit" class="button-primary" value="ذخيره کن" />
				</td>
			</tr>
		</table>
		</form>
	</div>';
	
	}
	protected static function admin_products_delete() {
		// delete and redirect
		global $wpdb;
		$table_name = $wpdb->prefix . "pfd_products";
		$id = $_GET["id"];
		$wpdb->query("DELETE FROM $table_name WHERE id = '$id'");
		
		echo '<script type="text/javascript"><!--
		window.location="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-file-download-products"';
		echo '//--></script>';
	}
    public static function admin_help() {
	echo '
	<div class="wrap">
            <p style="text-align:right;font-size:12px;">
            	<h2>راهنمایی استفاده</h2>
            <br>
            <code>[form_buy_vip descript=true]</code> : برای نمایش فرم خرید اشتراک و نمایش آخرین وضعیت اشتراک کاربر از این شورتکد در محتوای برگه ای خاص استفاده کنید.
            <br>
            
            <code>[form_buy_vip descript=false]</code> : عملکرد این شورتکد مانند مورد قبلی است ولی توضیحات مربوط به اشتراک ها را نمایش نمی دهد. (مناسب برای ساید بار)
            <br>
            
            <code>[vip_data]HELP[/vip_data]</code> : محتوایی را که می خواهید فقط به کاربران vip خود نمایش دهید در داخل این شورتکد قرار دهید. در این مثال کلمه HELP فقط به کاربران VIP نمایش داده می شود.
            <br>
            <code>[vip_linkdownload idproduct=1]</code> : با استفاده از این شورت کد می توانید محصولات فروشگاه را برای استفاده کاربران vip قرار دهید. در این مثال عدد 1 بیانکر شماره اختصاصی محصول می باشد.
            مقدار idproduct باید شماره اختصاصی آن محصول در صفحه محصولات فروشگاه باشد.
            
            <li>در صفحه افزودن نوشته و افزودن برگه شورتکد های مورد نیاز روی ادیتور وردپرس نمایش داده شده اند</li>
            
            <li>برای استفاده شورتکد ها در سایدبار از افزونه Shortcode Widget استفاده کنید</li>
            
            </p>
       
       </div>
	';

    }
	protected static function admin_products() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
		
		$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;
		$limit = 6;
		$offset = ( $pagenum - 1 ) * $limit;
		
		global $wpdb;
		$table_name = $wpdb->prefix . "pfd_products";
		$products = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC LIMIT $offset, $limit" ,ARRAY_A);
		
		
		$total = $wpdb->get_var( "SELECT COUNT(`id`) FROM {$wpdb->prefix}pfd_products" );
		$num_of_pages = ceil( $total / $limit );
		
		$cntx=0;
		
	echo '
	<div class="wrap">
		<h2>محصولات</h2>
		<table class="widefat post fixed" cellspacing="0">
			<thead>
				<tr>
					
					<th scope="col" id="name"  class="manage-column" style="">شماره آی دی</th>
					<th scope="col" id="name" width="50%" class="manage-column" style="">نام</th>
					<th scope="col" id="cost" class="manage-column" style="">قيمت</th>
					<th scope="col" id="downloads" class="manage-column num" style="">تعداد دانلود</th>
					<th scope="col" id="edit" class="manage-column num" style="">ويرايش</th>
					<th scope="col" id="delete" class="manage-column num" style="">حذف</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					
					<th scope="col" id="name"  class="manage-column" style="">شماره آی دی</th>
					<th scope="col" id="name" width="50%" class="manage-column" style="">نام</th>
					<th scope="col" id="cost" class="manage-column" style="">قيمت</th>
					<th scope="col" id="downloads" class="manage-column num" style="">تعداد دانلود</th>
					<th scope="col" id="edit" class="manage-column num" style="">ويرايش</th>
					<th scope="col" id="delete" class="manage-column num" style="">حذف</th>
				</tr>
			</tfoot>
			<tbody>';
				
				
				if (count($products) == 0) {
				echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="" colspan="5">هيچ محصولي موجود نيست</td>
				</tr>';
				} else {
				foreach ($products as $product) {
					$cntx++;
				echo '<tr class="alternate author-self status-publish iedit" valign="top">
					
					<td class="" style="color:#F00;"><b>'.$product['id'].'</b></td>
					<td class="post-title column-title"><strong><a class="row-title" href="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-file-download-products&action=edit&id='.$product['id'].'">'.$product['name'].'</a></strong></td>
					<td class="">'.$product['cost'].' ريال</td>
					<td class="" style="text-align:center;">'.$product['downloads'].'</td>
					<td class="" style="text-align:center;"><a href="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-file-download-products&action=edit&id='.$product['id'].'">ويرايش</a></td>
					<td class="" style="text-align:center;"><a href="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-file-download-products&action=delete&id='.$product['id'].'" onClick="if(confirm(\'آيا از حذف اين مورد اطمينان داريد؟ !\')) { return true;} else { return false;}">حذف</a></td>
				</tr>';
				} } 
			echo '</tbody>
		</table>
		<br>';
        
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'prev_text' => __( '&laquo;', 'aag' ),
			'next_text' => __( '&raquo;', 'aag' ),
			'total' => $num_of_pages,
			'current' => $pagenum
		) );
		
		if ( $page_links ) {
			echo '<center><div class="tablenav"><div class="tablenav-pages"  style="float:none; margin: 1em 0">' . $page_links . '</div></div>
		</center>';
		}
		
		
        echo '<br>
		<hr>
		<h2>اضافه نمودن محصول</h2>
		<form action="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-file-download-products&action=add" method="post">
		<table class="form-table">
			<tr valign="top">
				<th scope="row">نام محصول</th>
				<td><input type="text" name="product_name" style="width:250px;" value="" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">لينک محصول</th>
				<td><input type="text" name="product_url" style="width:400px;" value="" /><br />(لطفا اطمينان حاصل کنيد که اين لينک مخفي است<br />اين لينک پس از خريد موفق به خريدار نشان داده مي شود )</td>
			</tr>
			<tr valign="top">
				<th scope="row">قيمت محصول(به ازاي هر بار دانلود)</th>
				<td><input type="text" name="product_cost" style="width:150px;" value="" />ريال</td>
			</tr>
			<tr valign="top">
				<th scope="row">&nbsp;</th>
				<td>
					<input type="submit" class="button-primary" value="اضافه کن" />
				</td>
			</tr>
		</table>
		</form>
	</div>';
	
	}
	protected static function admin_products_add() {
		// get shit
		$name = $_POST["product_name"];
		$url = $_POST["product_url"];
		$cost = $_POST["product_cost"];
		global $wpdb;
		$table_name = $wpdb->prefix . "pfd_products";
		$wpdb->insert( $table_name, array('name' => $name, 'file' => $url, 'cost' => $cost, 'downloads' => 0, 'created_at' => time()), array( '%s', '%s', '%s', '%d', '%d') );
		echo '<script type="text/javascript"><!--
		window.location="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-file-download-products"';
		echo '//--></script>';
	}
	public static function admin_settings() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
	
	echo '<div class="wrap">
		<h2>تنظیمات</h2>
        <h3 style="color:#f00;">کلید مجوزدهی نکست  پی (Api Key) را از <a href="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-vip-settings">اینجا</a> وارد نمایید</h3>';
		
		if (isset($_GET['settings-updated'])) {
			echo '<div id="message" class="updated"><p>تنظيمات به روز شد!</p></div>';
		}
		echo '<form method="post" action="options.php">';
        
			settings_fields('pfd_options');
		
        	echo '<table class="form-table">
				
				<tr valign="top">
					<th scope="row">تاريخ انقضاي لينک بعد از...</th>
					<td><input type="text" name="expire_links_after" style="width:150px;" value="'.get_option('expire_links_after').'" /> روز (0 براي بي نهايت)<br />فعال کردن اين قسمت باعث مي شود لينک هاي شما پس از مدت تعيين شده غير فعال شوند</td>
				</tr>';
            echo '<tr valign="top">
					<th scope="row">مستقیم کردن لینک</th>
					<td><input type="text" name="nextpay_direct" style="width:150px;" value="'.get_option('nextpay_direct').'" /> روز (1 به معنای فعال)<br />فعال کردن اين قسمت باعث مي شود لينک هاي شما پس از پرداخت بصورت مستقیم نمایش داده شوند</td>
				</tr>';
				
				echo '<tr valign="top">
					<th scope="row">آدرس بازگشتي</th>
					<td><input type="text" name="nextpay_return_url" style="width:250px;" value="'.get_option('nextpay_return_url').'" /><br />لينک بازگشت به سايت شما پس از انجام تراکنش در درگاه nextpay.ir</td>
				</tr>';
				echo '<tr valign="top">
					<th scope="row">اطلاع رساني</th>
					<td><textarea name="email_message" style="width:400px;height:200px;">'.get_option('email_message').'></textarea><br />پس از خريد موفق اين متن براي خريدار به نمايش در خواهد آمد<br /><strong>لينک دانلود بصورت اتوماتيک در انتهاي اين متن قرار مي گيرد</strong><br />شما مي توانيد از متغير هاي زير استفاده کنيد: <br />[DOWNLOAD_LINK] [PRODUCT_NAME] [TRANSACTION_ID]<br /></td>
				</tr>
				<tr valign="top">
					<th scope="row">&nbsp;</th>
					<td>
						<input type="submit" class="button-primary" value="ذخیره تغییرات" />
					</td>
				</tr>
			</table>
		</form>
	</div>';
	}
	public static function admin_transactions() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
		
		
		
		
		$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;
		$limit = 6;
		$offset = ( $pagenum - 1 ) * $limit;
		
		
		
		global $wpdb;
		$table_name = $wpdb->prefix . "pfd_transactions";
		$products_name = $wpdb->prefix . "pfd_products";
		$orders_name = $wpdb->prefix . "pfd_orders";
		
				
		$transactions = $wpdb->get_results( "SELECT $table_name.order_code, $products_name.name, $table_name.first_name, $table_name.created_at, $table_name.last_name, $table_name.address_street, $table_name.address_city, $table_name.address_state, $table_name.address_zip, $table_name.address_country, $table_name.payment_fee, $table_name.payer_email,$table_name.mobile,  $orders_name.cost FROM $table_name JOIN $products_name ON $table_name.product_id = $products_name.id JOIN $orders_name ON $table_name.order_id = $orders_name.id ORDER BY $table_name.id DESC LIMIT $offset, $limit" ,ARRAY_A);
			
		$total = $wpdb->get_var( "SELECT COUNT($table_name.id) FROM $table_name JOIN $products_name ON $table_name.product_id = $products_name.id JOIN $orders_name ON $table_name.order_id = $orders_name.id");
		$num_of_pages = ceil( $total / $limit );
		
		$cntx=0;
		
		
	
	echo '<div class="wrap">
		<h2>فروش ها</h2>
		<table class="widefat post fixed" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" id="name" width="40%" class="manage-column" style="">شماره تراکنش, محصول</th>
					<th scope="col" id="name" width="20%" class="manage-column" style="">تاريخ</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style="">ایمیل موبایل</th>
                    
                    <th scope="col" id="name" width="20%" class="manage-column" style="">قيمت</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col" id="name" width="40%" class="manage-column" style="">شماره تراکنش, محصول</th>
					<th scope="col" id="name" width="20%" class="manage-column" style="">تاريخ</th>
                    <th scope="col" id="name" width="20%" class="manage-column" style="">ایمیل موبایل</th>
                    
                    <th scope="col" id="name" width="20%" class="manage-column" style="">قيمت</th>
				</tr>
			</tfoot>
			<tbody>';
				
				
				if (count($transactions) == 0) {
				
				echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="" colspan="7">هيج تراکنش وجود ندارد.</td>
				</tr>';
				
				} else {
				foreach ($transactions as $transaction) {
				
				echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="post-title column-title">'.$transaction['order_code'].'<br /><strong>'.$transaction['name'].'</strong></td>
					<td class="">';
					echo strftime("%a, %B %e, %Y %r", $transaction['created_at']);
					echo '<br />(';
					echo self::relative_time($transaction["created_at"]);
					echo ' ago)</td><td class="">'.$transaction['payer_email'].'<br>'.$transaction['mobile'].'</td><td class="">'.$transaction['cost'].' ريال</td></tr>';
				} } 
		echo '</tbody>
		</table>
        <br>';
        
        
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'prev_text' => __( '&laquo;', 'aag' ),
			'next_text' => __( '&raquo;', 'aag' ),
			'total' => $num_of_pages,
			'current' => $pagenum
		) );
		
		if ( $page_links ) {
			echo '<center><div class="tablenav"><div class="tablenav-pages"  style="float:none; margin: 1em 0">' . $page_links . '</div></div>
		</center>';
		}
		
        echo '<br>
		<hr>
	</div>';
	}
	public static function media_button($context){
		$image_url = plugins_url( 'images/basket.png' , __FILE__ );
		$more = '<a href="#TB_inline?width=350&inlineId=nextpay_file_download_form" class="thickbox" title="قرارد دادن لينک پرداخت nextpay"><img src="' . $image_url . '" alt="قرارد دادن لينک پرداخت nextpay" /></a>';
		return $context . $more;
	}
	public static function vip_media_button($context){
		$image_url = plugins_url( 'images/vip.png' , __FILE__ );
		$more = '<a href="#TB_inline?width=350&inlineId=vip_linkdownload_form" class="thickbox" title="قرارد دادن لينک پرداخت nextpay و لینک دانلود vip"><img src="' . $image_url . '" alt="vip" /></a>';
		return $context . $more;
	}
	public static function vip_data_button($context){
		$image_url = plugins_url( 'images/vipdata.png' , __FILE__ );
		$more = '<a href="#TB_inline?width=350&inlineId=vip_data_form" class="thickbox" title="قرار دادن شورتکد محتوای vip"><img src="' . $image_url . '" alt="قرار دادن شورتکد محتوای vip" /></a>';
		return $context . $more;
	}
	
	
	public static function add_vip_data_form(){
		echo "
	<script type=\"text/javascript\">
		function insert_shortcode_vipdata()
		{
		textvipdata = jQuery(\"#textvipdata\").val()
		
		construct = '[vip_data]'+textvipdata+'[/vip_data]';
		var wdw = window.dialogArguments || opener || parent || top;
		wdw.send_to_editor(construct);
		}
	</script>";
	
	echo '<div id="vip_data_form" style="display:none;">
		<div class="wrap" style="text-align:right;direction:rtl;">
			<div>	
				<div style="padding:15px 15px 0 15px;">
					<h3 style="font-size:16pt"><br />قرار دادن محتوای مخصوص کاربران VIP</h3>
                    <span> [vip_data] محل قرار دادن متن یا لینک ویژه [/vip_data] </span>
				</div>
				<div style="padding:15px 15px 0 15px;">
                <textarea name="textvipdata" id="textvipdata" style="width:90%; height:150px;"></textarea>
				</div>';
				
				echo '<div style="padding:15px;">
					<input type="button" class="button-primary" value="گذاشتن در نوشته" onClick="insert_shortcode_vipdata();"/>&nbsp;&nbsp;&nbsp;&nbsp;
                    <input type="button" class="button" value="بستن" onClick="tb_remove();"/>
                    
				</div>
			</div>
		</div>
	</div>';
}
	
	
	
	public static function add_pfd_form() {
	
	echo "<script type=\"text/javascript\">
		function insert_pfd_button(){
			product_id = jQuery('#product_selector').val()
			image = jQuery('#button_image_url').val()
            construct = '<form name=\"frm_nextpay' + product_id + '\" action=\"".get_option('siteurl')."/?checkout=' + product_id + '\" method=\"post\"><input type=\"image\" name=\"submit\" src=\"' + image + '\" value=\"1\"></form>';";
			echo "var wdw = window.dialogArguments || opener || parent || top;
			wdw.send_to_editor(construct);
		}";
		echo "function insert_pfd_link(){
			product_id = jQuery('#product_selector').val()
            construct = '<form name=\"frm_nextpay' + product_id + '\" action=\"".get_option('siteurl')."/?checkout=' + product_id + '\" method=\"post\"><input type=\"image\" src=\"\" value=\"' + image + '\"></form>';";
			echo "var wdw = window.dialogArguments || opener || parent || top;
			wdw.send_to_editor(construct);
		}
	</script>";
	echo '<div id="nextpay_file_download_form" style="display:none;">
		<div class="wrap" style="text-align:right;direction:rtl;">
			<div>	
				<div style="padding:15px 15px 0 15px;">
					<h3 style="font-size:16pt"><br />قرارد دادن لينک پرداخت nextpay</h3>
					<span>لطفا محصول مورد نظرتان را از لينک زير انتخاب نماييد</span>
				</div>
				<div style="padding:15px 15px 0 15px;">
					<table width="100%">
						<tr>
							<td width="150"><strong>محصول</strong></td>
							<td>';
                            
									global $wpdb;
									$table_name = $wpdb->prefix . "pfd_products";
									$products = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id ASC;" ,ARRAY_A);
									if (count($products) == 0)
									{
										echo 'محصولي وجود ندارد. <a href="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-file-download-products">نوشته خود را ذخيره کنيد و سپس اينجا کليک نماييد.</a>';
									
									} 
									else 
									{
										echo '<select id="product_selector">';
										foreach($products as $product)
										{
									
											echo '<option value="'.$product["id"].'">'.$product["name"].' ('.$product["cost"].' ريال)</option>';
									
										}
										echo '</select>';
									}
									
							echo '</td>
						</tr>
						<tr>
							<td width="135"><strong>لينک تصوير پرداخت:</strong></td>
							<td><input type="text" id="button_image_url" value="'.plugins_url("images/paynow.png",__FILE__).'" style="width:220px;" /></td>
						</tr>
					</table>
				</div>';
				echo '<div style="padding:15px;">
					<input type="button" class="button-primary" value="قرار دادن Button" onClick="insert_pfd_button();"/>&nbsp;&nbsp;&nbsp;&nbsp;<input type="button" class="button" value="قرار دادن لينک" onClick="insert_pfd_link();"/>&nbsp;&nbsp;&nbsp;&nbsp;
                    <input type="button" class="button" value="بستن" onClick="tb_remove();"/>
                    
				</div>
			</div>
		</div>
	</div>';
	}
	protected static function ipn() {
		echo "<br/><div align='center' dir='rtl' style='font-family:tahoma;font-size:12px;'><b>نتیجـــه تـــراکنـش</b></div><br />";

        $api_key = get_option('vip_nextpay_api');;
        $trans_id = $_POST['trans_id'];
        $order_id = $_POST['order_id'];



		if (isset($trans_id) AND isset($order_id)){
            // find with order id
            global $wpdb;
            $table_name = $wpdb->prefix . "pfd_orders";
            $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE order_code = '%s' AND fulfilled = 0",$order_id) , ARRAY_A, 0);
            if(!$order){
                exit();
            }
		}else{
			exit();
		}

        $amount = $order["cost"] / 10;

        $client = new SoapClient('https://api.nextpay.org/gateway/verify.wsdl', array('encoding' => 'UTF-8'));

        $result = $client->PaymentVerification(
            array(
                'api_key' => $api_key,
                'trans_id'  => $trans_id,
                'amount'	 => $amount,
                'order_id'	=> $order_id
            )
        );
        $result = $result->PaymentVerificationResult;

		$this_script = get_option('siteurl');

        if(intval($result->code) == 0){
			$wpdb->update( $table_name, array('fulfilled' => 1), array('id' => $order["id"]));
			$table_name = $wpdb->prefix . "pfd_products";
			$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$order["product_id"]) , ARRAY_A, 0);
			$wpdb->update( $table_name, array('downloads' => $product["downloads"] + 1), array('id' => $product["id"]));
			$trans = array();
			// vars we want to extract
			/*$fields = "protection_eligibility address_status payer_id tax payment_date payment_status first_name last_name payer_status business address_name address_street address_city address_state address_zip address_country_code address_country quantity verify_sign payer_email txn_id payment_type receiver_email receiver_id txn_type item_name mc_currency item_number residence_country custom receipt_id transaction_subject payment_fee payment_gross";
			$fields_a = explode(" ",$fields);
			foreach($fields_a as $field) {
				$trans[$field] = isset($_POST[$field]) ? $_POST[$field] : NULL;
			}*/
			$trans["product_id"] = $product["id"];
			$trans["order_code"] = $trans_id;
			$trans["order_id"] = $order["id"];
			@session_start();
			$trans["payer_email"] = $_SESSION['email'];
			$trans["mobile"]=$_SESSION['mobile'];
			$trans["created_at"] = time();
			// insert into transactions
			$table_name = $wpdb->prefix . "pfd_transactions";
			$wpdb->insert($table_name, $trans);
			// download link
			if(get_option("nextpay_direct") == 1){
				$download_link = $product["file"];
				$download_name = $product["name"];
				$download_link = "<a href='$download_link'>$download_name</a>";
			}else{
				$download_link = get_option('siteurl') . "/?download=" . $trans_id;
				$download_link = "<a href='$download_link'>$download_link</a>";
			}
            
			// get email text
			$emailtext = get_option('email_message');
			$emailtext = str_replace("[DOWNLOAD_LINK]",$download_link,$emailtext);
			$emailtext = str_replace("[PRODUCT_NAME]",$product["name"],$emailtext);
			$emailtext = str_replace("[TRANSACTION_ID]",$_POST["trans_id"],$emailtext);
			$emailtext = $emailtext . "<br /><br />لينک دانلود شما:<br />" . $download_link;
			// fantastic, now send them a message
			$message = $emailtext;
			echo "<div align='center' dir='rtl' style='font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%'>تراکنش شما <font color='green'><b>مـوفق بود</b></font>.<br/><p align='right' style='margin-right:15px'>".nl2br($message)."</p><a href='",get_option('siteurl'),"'>بازگشت به صفحه اصلي</a><br/><br/></div>";
			@session_start();
			$headers = "From: <no-reply>\n";
			$headers .= "MIME-Version: 1.0\n";
			$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
			
			if(mail($_SESSION['email'], 'اطلاعات پرداخت', $emailtext, $headers) == false)
			{
				wp_mail( $_SESSION['email'], 'اطلاعات پرداخت', $emailtext, $headers );
			}
			
		}else{
            echo "<div align='center' dir='rtl' style='font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%'>تراکنش شما <font color='red'><b>نـاموفق بود</b></font>.<br/><p align='right' style='margin-right:15px'> ممکن است به یکی از دلایل زیر باشد:<br/>1- ممکن است ارتباط شما با دروازه پرداخت بانک برقرار نشده باشد<br/>2- ممکن است از انجام عملیات پرداخت منصرف شده باشید<br/>3- ممکن است سرعت اینترنت شما درحال حاضر کم باشد و قادر به باز کردن درگاه پرداخت بانک نباشید.<br/> لطفا به صفحه اصلی سایت بازگشته و مجددا خرید خود را انجام دهید.</p><a href='",get_option('siteurl'),"'>بازگشت به صفحه اصلي</a><br/><br/></div>";
        }
	}
   
	protected static function get_email() {
		@session_start();
		$rand = rand(1000,9999);
		$_SESSION['captcha'] = $rand;
		
        
echo '<html>
<link rel="stylesheet"  media="all" type="text/css" href="'.plugins_url('style.css',__FILE__).'">
<body class="vipbody">	
<div class="mrbox2" > 
<h3><span>اطلاعات تکمیلی برای خرید آنلاین</span></h3>';
		if(isset($_SESSION['ErrorInput']))
		{
			echo $_SESSION['ErrorInput'];
			unset($_SESSION['ErrorInput']);
		}
	
        echo '<br />
        <form name="frm1" method="post">
        <table style="margin:0px auto;width:300px;">
        <tr>
        <td>ایمیل:</td>
        <td><input  type="text" name="email" id="email" style="text-align:left;" value="'.$_POST['email'].'" /><r style="color:#F00;"">*</r></td>
        </tr>';
        
        echo '<tr>
        <td>شماره همراه:</td>
        <td><input  type="text" name="mobile" id="mobile" style="text-align:left;" value="" /><r style="color:#F00;""></r></td>
        </tr>
        </table>
        <label class="title"> تصویر امنیتی</label>
          <input type="text" id="captcha" min="100" max="100000" name="captcha" class="CapchaInput" />
          <div class="captchalogin" style="background-color:#2064af;text-align: center;color: #FFFFFF;font-weight: bold;">'.$rand.'</div>
          <br />
          <br />
          <p align="center"><font color="#0066FF">برای ورود به درگاه پرداخت روی کلید زیر کلیک کنید.</font></p>
          <input type="submit" name="submit" id="submit" value="&nbsp;" class="dlbtn"/>
          <br />
        </form>
</div>
</body>
</html>'; 
        
	}
	public static function var_listener() {
		$SiteURL=get_option('siteurl');
         if(get_query_var("checkout")==NULL) {
			if(get_query_var("download")==NULL) {
				if (get_query_var("pfd_action") == "ipn") {
					self::ipn();
					exit();
				}
			} else {
				$id = $_GET["download"];
				global $wpdb;
				$table_name = $wpdb->prefix . "pfd_transactions";
				$transaction = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE order_code = %s",$id ), ARRAY_A, 0);
				if ($transaction==NULL) {
					die("فايل مورد نظر يافت نشد.");
				} else {
					$table_name = $wpdb->prefix . "pfd_products";
					$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$transaction["product_id"]), ARRAY_A, 0);
					// get option for days
					$daysexpire = get_option('expire_links_after');
					if ($daysexpire == 0) {
						// don't check
					} else {
						// check for expiry
						// transaction created at should be larger than now - x days
						$nowminus = time() - ($daysexpire*86400);
						if ($transaction["created_at"] > $nowminus) {
							// good
						} else {
							die("مدت زمان دانلود اين فايل به اتمام رسيده است.");
						}
					}
					// force download
					header('Content-disposition: attachment; filename=' . basename($product["file"]));
					header('Content-Type: application/octet-stream');
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
					header('Expires: 0');
					$result = wp_remote_get($product["file"]);
					echo $result['body'];					
					die();
				}
			}
		} else {
			@session_start();
			if(isset($_POST['submit'])){
				
				$_SESSION['ErrorInput']='';
				$_captcha=$_SESSION['captcha'];
				$_email=$_POST['email'];
				$_mobile=$_POST['mobile'];
				$_status_captcha='captcha';
				
				if(isset($_POST['status_captcha']))
				{
					$_status_captcha=$_POST['status_captcha'];
				}
				
				if (!filter_var($_email, FILTER_VALIDATE_EMAIL))
				{
					$_SESSION['ErrorInput']='<ErrorMsg>ایمیل وارد شده نامعتبر است</ErrorMsg>';
					self::get_email();
					exit();
				}
				if($_status_captcha!='no_captcha')
				{				
					if($_POST['captcha']!=$_captcha)
					{
	
						$_SESSION['ErrorInput']='<ErrorMsg>تصویر امنیتی را درست وارد نمایید</ErrorMsg>';
	
						self::get_email();
	
						exit();
	
					}
				}
				
				$_mobile=trim($_mobile);
				/*$pattern ="/(\+98|0)?9\d{9}/";
				if(!preg_match($pattern,$_mobile))
				{
					$_SESSION['ErrorInput']='<ErrorMsg>شماره همراه راصحیح وارد نمایید
					09101234567 یا 989101234567+ </ErrorMsg>';
					self::get_email();
					exit();
				}*/
				
				$_SESSION['email'] = $_email;
				$_SESSION['mobile'] = $_mobile;
					
				$product_id = get_query_var("checkout");
	
				global $wpdb;
				$table_name = $wpdb->prefix . "pfd_products";
	
				// get product
				$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$product_id) , ARRAY_A, 0);
	
				// construct order
				$table_name = $wpdb->prefix . "pfd_orders";

				$client = new SoapClient('http://api.nextpay.org/gateway/token.wsdl', array('encoding' => 'UTF-8'));
				$api_key = get_option('vip_nextpay_api');
				$amount = $product["cost"] / 10;
				$order_id = md5(uniqid(rand(), true));
				$redirect = get_option('siteurl') . "/?pfd_action=ipn";

				$result = $client->TokenGenerator(
					array(
						'api_key' 	=> $api_key,
						'order_id'	=> $order_id,
						'amount' 		=> $amount,
						'callback_uri' 	=> $redirect
					)
				);

				$result = $result->TokenGeneratorResult;


				if(intval($result->code) == -1){
					$go = "https://api.nextpay.org/gateway/payment/" . $result->trans_id;
					$wpdb->insert( $table_name, array('product_id' => $product_id, 'order_code' => $order_id, 'fulfilled' => 0, 'created_at' => time(), 'cost' => $product["cost"]), array( '%d', '%s', '%d', '%d', '%s') );
					header("Location: $go");
				}else{
					
					$html ='<html><head>
						<link media="all" rel="stylesheet" type="text/css" href="'.plugins_url('style.css',__FILE__).'"></head><body class="vipbody"><div class="mrbox2">';
						$html .='<b>در برقراري ارتباط با درگاه پرداخت nextpay مشکلي بوجود آمده است<br>';
						$html .='لطفا به مدیر اطلاع دهید<br>کد خطا : '.$result->code.'</b><br><br>';
						$html .='<a class="mrbtn_green" href="'.get_option('siteurl').'">بازگشت به صفحه اصلي</a>';
						$html .='</div></body></html>';			
						echo $html;
						exit();
				}
			}else{
				self::get_email();
				exit();
			}
	}
    }
	// make sure we have the nextpay action listener available
	public static function register_vars($vars) {
		$vars[] = "pfd_action";
		$vars[] = "checkout";
		$vars[] = "download";
		$vars[] = "vip_action";
		$vars[] = "buy_account_vip";
		$vars[] = "vipdownload";
		return $vars; // return to wordpress
	}
	
	
	
	
	
	
	
	
	// ALL Down Code ----> VIP
	
	
	
	
	public static function admin_vip_router() {
		$action = '';
		if (!empty($_REQUEST['action'])) {
			$action = $_REQUEST['action'];
		}
		switch ($action) {
			case 'edit':
				return self::admin_vip_account_edit();
				break;
			case 'delete':
				return self::admin_vip_account_delete();
				break;
			case 'add':
				return self::admin_vip_account_add();
				break;
			default:
				return self::admin_vip_accounts();
		}
	}
	protected static function admin_vip_account_edit() {
		global $wpdb;
		$table_name = $wpdb->prefix . "vip_accounts";
		
		if (isset($_POST["name"])) {
			$name = $_POST["name"];
			$descript = $_POST["descript"];
			$cost = $_POST["cost"];
			$day = $_POST["day"];
			$per_day=$_POST["per_day"];
						
			$wpdb->update( $table_name, array('name' => $name, 'descript' => $descript, 'cost' => $cost,'day' => $day,'per_day' => $per_day,), array('id' => $_GET["id"]), array( '%s', '%s', '%d', '%d', '%d'));
		}
		
		
		$account = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$_GET["id"]) , ARRAY_A, 0);
	
echo '<div class="wrap">
  <h3>ويرايش اشتراک: '.$account['name'].'</h3>
  <a href="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-vip-accounts">&laquo; بازگشت به صفحه اشتراک ها</a>
  <form action="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-vip-accounts&action=edit&id='.$_GET['id'].'" method="post">
    <table class="form-table">
      <tr valign="top">
        <th scope="row">نام اشتراک</th>
        <td><input type="text" name="name" style="width:250px;" value="'.str_replace('"','\"',$account["name"]).'" /></td>
      </tr>
      <tr valign="top">
        <th scope="row">توضیحات</th>
        <td>';
        
        echo '<textarea name="descript" style="width:400px;" cols="" rows="">'.str_replace('"','\"',$account["descript"]).'</textarea>
        </td>
        </tr>';
      
      echo '<tr valign="top">
        <th scope="row">قيمت اشتراک</th>
        <td><input type="text" name="cost" style="width:150px;" value="'.str_replace('"','\"',$account["cost"]).'" />
          ريال</td>
      </tr>
      <tr valign="top">
        <th scope="row">تعداد روز اشتراک</th>
        <td><input type="text" name="day" style="width:100px;" value="'.str_replace('"','\"',$account["day"]).'" />
          </td>
      </tr>
      <tr valign="top">
        <th scope="row">تعداد دانلود مجاز در یک روز</th>
        <td><input type="text" name="per_day" style="width:100px;" value="'.str_replace('"','\"',$account["per_day"]).'" />
        
        <br><span>1- : تعداد دانلود روزانه نامحدود</span>
        <br><span>0 : کاربران این اشتراک فقط امکان مشاهده مطالب vip را دارند (شامل :متن ، لینک ، تصویر و هر چه شما در بین شورتکد vip_data قرار دهید ) اما امکان  دانلود روزانه vip ندارند</span>
          </td>
      </tr>';
      
      echo '<tr valign="top">
        <th scope="row">&nbsp;</th>
        <td><input type="submit" class="button-primary" value="ذخيره کن" /></td>
      </tr>
    </table>
  </form>
</div>';
	}
	protected static function admin_vip_account_delete() {
		// delete and redirect
		global $wpdb;
		$table_name = $wpdb->prefix . "vip_accounts";
		$id = $_GET["id"];
		$wpdb->query("DELETE FROM $table_name WHERE id = '$id'");
		
		echo '<script type="text/javascript"><!--
		window.location="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-vip-accounts"';
		echo '//--></script>';
	}
	 
	protected static function admin_vip_accounts() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
	
echo '<div class="wrap">
  <h3>لیست اشتراک ها</h3>
  <table class="widefat post fixed" cellspacing="0">
    <thead>
      <tr>
        <th scope="col" id="id"  class="manage-column" style="">#</th>
        <th scope="col" id="name" class="manage-column" style="">نام</th>
        <th scope="col" id="descript" width="30%" class="manage-column" style="">توضیحات</th>
        <th scope="col" id="cost" class="manage-column" style="">قيمت</th>
        <th scope="col" id="day" class="manage-column num" style="">تعداد روز</th>
        <th scope="col" id="per_day" class="manage-column num" style="">دانلود روزانه</th>
        <th scope="col" id="delete" class="manage-column num" style="">حذف</th>
      </tr>
    </thead>
    <tfoot>
      <tr>
        <th scope="col" id="id"  class="manage-column" style="">#</th>
        <th scope="col" id="name" class="manage-column" style="">نام</th>
        <th scope="col" id="descript" width="30%" class="manage-column" style="">توضیحات</th>
        <th scope="col" id="cost" class="manage-column" style="">قيمت</th>
        <th scope="col" id="day" class="manage-column num" style="">تعداد روز</th>
        <th scope="col" id="per_day" class="manage-column num" style="">دانلود روزانه</th>
        <th scope="col" id="delete" class="manage-column num" style="">حذف</th>
      </tr>
    </tfoot>
    <tbody>';
      
				global $wpdb;
				$table_name = $wpdb->prefix . "vip_accounts";
				$accounts = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id desc" ,ARRAY_A);
				if (count($accounts) == 0) {
				
      echo '<tr class="alternate author-self status-publish iedit" valign="top">
        <td class="" colspan="5">هيچ اشتراکی موجود نيست</td>
      </tr>';
      
				} else {
				foreach ($accounts as $account) {
					$user_info = get_userdata(intval($account['iduser']));
				
      echo '<tr class="alternate author-self status-publish iedit" valign="top">
        <td class="">'.$account['id'].'</td>
        <td>
		<a class="row-title" href="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-vip-accounts&action=edit&id='.$account['id'].'">'.$account['name'].'</a></td>';
       echo '<td>'.$account['descript'].'</td>
       <td>'.$account['cost'].'</td>
       <td>'.$account['day'].'</td>
       <td>'.$account['per_day'].'</td>
       <td><a class="row-title" href="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-vip-accounts&action=delete&id='.$account['id'].'">حذف</a></td>
      </tr>';
      } }
    echo '</tbody>
  </table>
  <h3>اضافه نمودن اشتراک</h3>
  <form action="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-vip-accounts&action=add" method="post">
    <table class="form-table">
      <tr valign="top">
        <th scope="row">نام اشتراک</th>
        <td><input type="text" name="name" style="width:250px;" /></td>
      </tr>
      <tr valign="top">
        <th scope="row">توضیحات</th>
        <td>        
        <textarea name="descript" style="width:400px;" cols="" rows=""></textarea>
        </td>
        </tr>
      <tr valign="top">
        <th scope="row">قيمت اشتراک</th>
        <td><input type="text" name="cost" style="width:150px;" />
          ريال</td>
      </tr>
      <tr valign="top">
        <th scope="row">تعداد روز اشتراک</th>
        <td><input type="text" name="day" style="width:100px;" />
          </td>
      </tr>
      <tr valign="top">
        <th scope="row">تعداد دانلود مجاز در یک روز</th>
        <td><input type="text" name="per_day" style="width:100px;" />
        
        <br><span>1- : تعداد دانلود روزانه نامحدود</span>
        <br><span>0 : کاربران این اشتراک فقط امکان مشاهده مطالب vip را دارند (شامل :متن ، لینک ، تصویر و هر چه شما در بین شورتکد vip_data قرار دهید ) اما امکان  دانلود روزانه vip ندارند</span>
        
          </td>
      </tr>
      
      
      <tr valign="top">
        <th scope="row">&nbsp;</th>
        <td><input type="submit" class="button-primary" value="ذخيره کن" /></td>
      </tr>
    </table>
  </form>
</div>';
	}
	protected static function admin_vip_account_add() {
		// get shit
		$name = $_POST["name"];
		$descript = $_POST["descript"];
		$cost = $_POST["cost"];
		$day = $_POST["day"];
		$per_day = $_POST["per_day"];
		global $wpdb;
		$table_name = $wpdb->prefix . "vip_accounts";
		$wpdb->insert( $table_name, array('name' => $name, 'descript' => $descript, 'cost' => $cost, 'day' => $day, 'per_day' => $per_day), array( '%s', '%s', '%d', '%d', '%d') );
		
		echo '<script type="text/javascript"><!--
		window.location="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-vip-accounts"';
		echo '//--></script>';
		
	}
	public static function admin_settingsvip() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
	
echo '<div class="wrap">
  <h3>تنظيمات</h3>';
  
		if (isset($_GET['settings-updated'])) {
			echo '<div id="message" class="updated"><p>تنظيمات به روز شد!</p></div>';
		}
  echo '<form method="post" action="options.php">';
    settings_fields('vip_options');
    echo '<table class="form-table">
      <tr valign="top">
        <th scope="row">API</th>
        <td><input type="text" name="vip_nextpay_api" style="width:300px;" value="'.get_option('vip_nextpay_api').'" />
          <br />
          API درگاه شما در سايت nextpay . </td>
      </tr>
      
      <tr valign="top">
        <th scope="row">آدرس بازگشتي</th>
        <td><input type="text" name="vip_nextpay_return_url" style="width:250px;" value="'.get_option('vip_nextpay_return_url').'" />
          <br />
          لينک بازگشت به سايت شما پس از انجام تراکنش در درگاه nextpay.ir</td>
      </tr>';
      
	  echo '<tr valign="top">
        <th scope="row">پیام vip نبودن کاربر</th>
        <td><textarea name="vip_message_novip" style="width:400px;height:200px;">'.get_option('vip_message_novip').'</textarea>
          <br />
          این پیام برای کاربرانی نمایش داده می شود که vip نیستند<br />
          </td>
      </tr>';
      
      echo '<tr valign="top">
        <th scope="row">اطلاع رساني</th>
        <td><textarea name="vip_message_email" style="width:400px;height:200px;">'.get_option('vip_message_email').'</textarea>
          <br />
          پس از خرید اشتراک vip این پیام ایمیل و نمایش داده می شود<br />
          شما مي توانيد از متغير هاي زير استفاده کنيد: <br />
          [ACCOUNT_NAME] [TRANSACTION_ID]<br /></td>
      </tr>
    
      <tr valign="top">
        <th scope="row">&nbsp;</th>
        <td><input type="submit" class="button-primary" value="ذخیره تغییرات" /></td>
      </tr>
    </table>
  </form>
</div>';
	}
	
	
	
	
	public static function admin_orders() {
		if (!current_user_can('manage_options'))  {
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}
		
		$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;
		$limit = 6;
		$offset = ( $pagenum - 1 ) * $limit;
		
		
		global $wpdb;
		$orders_name = $wpdb->prefix . "vip_orders";
		$accounts_name = $wpdb->prefix . "vip_accounts";
		$users_name = $wpdb->prefix . "users";
		$orders = $wpdb->get_results( "SELECT $orders_name.iduser,$orders_name.fulfilled, $orders_name.idaccount, $orders_name.order_code , $orders_name.cost, $orders_name.created_at, $users_name.user_login,$accounts_name.name FROM $orders_name JOIN $accounts_name ON $orders_name.idaccount = $accounts_name.id JOIN $users_name ON $orders_name.iduser = $users_name.ID ORDER BY $orders_name.id DESC LIMIT $offset, $limit" ,ARRAY_A);
		
		
		$total = $wpdb->get_var( "SELECT COUNT($orders_name.id) FROM $orders_name JOIN $accounts_name ON $orders_name.idaccount = $accounts_name.id JOIN $users_name ON $orders_name.iduser = $users_name.ID" );
		
		$num_of_pages = ceil( $total / $limit );
		
		$cntx=0;
		
	
echo '<div class="wrap">
  <h3>لیست کاربران vip</h3>
  <table class="widefat post fixed" cellspacing="0">
    <thead>
      <tr>
        <th scope="col" id="name" width="" class="manage-column" style="width:30px">#</th>
        <th scope="col" id="name" width="" class="manage-column" style="">اشتراک</th>
        <th scope="col" id="name" width="" class="manage-column" style="">کاربر</th>
        <th scope="col" id="name" width="" class="manage-column" style="">تراکنش</th>
        <th scope="col" id="name" width="30%" class="manage-column" style="">تاريخ</th>
        <th scope="col" id="name" width="" class="manage-column" style="">قيمت ريال</th>
        <th scope="col" id="name" width="" class="manage-column" style="">وضعیت</th>
      </tr>
    </thead>
    <tfoot>
      <tr>
        <th scope="col" id="name" width="" class="manage-column" style="width:30px">#</th>
        <th scope="col" id="name" width="" class="manage-column" style="">اشتراک</th>
        <th scope="col" id="name" width="" class="manage-column" style="">کاربر</th>
        <th scope="col" id="name" width="" class="manage-column" style="">تراکنش</th>
        <th scope="col" id="name" width="30%" class="manage-column" style="">تاريخ</th>
        <th scope="col" id="name" width="" class="manage-column" style="">قيمت ريال</th>
        <th scope="col" id="name" width="" class="manage-column" style="">وضعیت</th>
      </tr>
    </tfoot>
    <tbody>';
				if (count($orders) == 0) {
				
      echo '<tr class="alternate author-self status-publish iedit" valign="top">
        <td class="" colspan="7">هيج تراکنش وجود ندارد.</td>
      </tr>';
				} else {
				foreach ($orders as $order) { $cntx++;
				
      echo '<tr class="alternate author-self status-publish iedit" valign="top">
        <td class="">'.$cntx.'</td>
        <td class="">'.$order['name'].'</td>
        <td class="">'.$order['user_login'].'</td>
        <td class="">'.$order['order_code'].'</td>
        <td class="">';
		echo strftime("%F %r", $order['created_at']);
		echo '<br />(';
		echo self::relative_time($order["created_at"]);
		echo ' ago)</td><td class="">'.$order['cost'].'</td>
        <td class="">';
		echo (intval($order['fulfilled'])==1)?"موفق":"ناموفق";
		echo '</td></tr>';
       } } 
    echo'</tbody>
  </table>
  <br>';
        
		$page_links = paginate_links( array(
			'base' => add_query_arg( 'pagenum', '%#%' ),
			'format' => '',
			'prev_text' => __( '&laquo;', 'aag' ),
			'next_text' => __( '&raquo;', 'aag' ),
			'total' => $num_of_pages,
			'current' => $pagenum
		) );
		
		if ( $page_links ) {
			echo '<center><div class="tablenav"><div class="tablenav-pages"  style="float:none; margin: 1em 0">' . $page_links . '</div></div>
		</center>';
		}
	
        echo '<br></div>';
	}
	protected static function ipnvip() {
		date_default_timezone_set("Asia/Tehran");
		
		echo "<br/><div align='center' dir='rtl' style='font-family:tahoma;font-size:12px;'><b>نتیجـــه تـــراکنـش</b></div><br />";
		


		
        $trans_id = $_POST['trans_id'];
		$order_id = $_POST['order_id'];


		if (isset($trans_id) AND isset($order_id)){
			// find with order id
			global $wpdb;
			$order_name = $wpdb->prefix . "vip_orders";
			$order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $order_name WHERE order_code = '%s' AND fulfilled = 0",$order_id) , ARRAY_A, 0);
			if(!$order){
				exit();
			}
		}else{
			exit();
		}

		$amount = $order["cost"] / 10;
		$api_key = get_option('vip_nextpay_api');

		$client = new SoapClient('https://api.nextpay.org/gateway/verify.wsdl', array('encoding' => 'UTF-8'));

		$result = $client->PaymentVerification(
			array(
				'api_key' => $api_key,
				'trans_id'  => $trans_id,
				'amount'	 => $amount,
				'order_id'	=> $order_id
			)
		);
		$result = $result->PaymentVerificationResult;


		if(intval($result->code) == 0){
			// find with order id
			
			$account_name = $wpdb->prefix . "vip_accounts";
			$account = $wpdb->get_row($wpdb->prepare("SELECT * FROM $account_name WHERE id = %d ",$order["idaccount"]) , ARRAY_A, 0);
			
			$wpdb->update( $order_name, array('fulfilled' => 1), array('id' => $order["id"]));
			
			
			//Add Information to Meta Users
			
			$user_id=$order['iduser'];
			$Exp_Vip=date('Y-m-d', strtotime('+'.$account['day'].' day', strtotime("now")));
			$Last_Vip_Name=$account['name'];
			$Last_Buy_Vip=$order['created_at'];
			$Exp_Per_Day=$account['per_day'];
			$Extant_Daily=$account['per_day'];
			
			update_user_meta( $user_id, 'exp_vip', $Exp_Vip);
			update_user_meta( $user_id, 'last_vip_name', $Last_Vip_Name);
			update_user_meta( $user_id, 'last_buy_vip', $Last_Buy_Vip);
			update_user_meta( $user_id, 'exp_per_day', $Exp_Per_Day);
			update_user_meta( $user_id, 'extant_daily', $Extant_Daily);				
			
			// get email text
			$emailtext = get_option('vip_message_email');
			$emailtext = str_replace("[ACCOUNT_NAME]",$account["name"],$emailtext);
			$emailtext = str_replace("[TRANSACTION_ID]",$trans_id, $emailtext);
			// fantastic, now send them a message
			$message = $emailtext;
			echo "<div align='center' dir='rtl' style='font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%'>تراکنش شما <font color='green'><b>مـوفق بود</b></font>.<br/><p align='right' style='margin-right:15px'>".nl2br($message)."</p><a href='",get_option('siteurl'),"'>بازگشت به صفحه اصلي</a><br/><br/></div><br>";
			
			$headers = "From: <no-reply>\n";
			$headers .= "MIME-Version: 1.0\n";
			$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
			
			$user_info=get_userdata(intval($order['iduser']));
			
			mail($user_info->user_email,'اطلاعات پرداخت خرید اشتراک',$emailtext,$headers);
		}else{
            echo "<div align='center' dir='rtl' style='font-family:tahoma;font-size:11px;border:1px dotted #c3c3c3; width:60%; line-height:20px;margin-left:20%'>تراکنش شما <font color='red'><b>نـاموفق بود</b></font>.<br/><p align='right' style='margin-right:15px'> ممکن است به یکی از دلایل زیر باشد:<br/>1- ممکن است ارتباط شما با دروازه پرداخت بانک برقرار نشده باشد<br/>2- ممکن است از انجام عملیات پرداخت منصرف شده باشید<br/>3- ممکن است سرعت اینترنت شما درحال حاضر کم باشد و قادر به باز کردن درگاه پرداخت بانک نباشید.<br/> لطفا به صفحه اصلی سایت بازگشته و مجددا خرید خود را انجام دهید.</p><a href='",get_option('siteurl'),"'>بازگشت به صفحه اصلي</a><br/><br/></div>";
        }
	}
	
	
	protected static function get_captcha() {
			
		@session_start();
		$rand = rand(1000,9999);
		$_SESSION['captcha'] = $rand;
		// Html Code For From
		    
echo '<html>
<link rel="stylesheet" media="all" type="text/css" href="'.plugins_url('style.css',__FILE__).'">
<body class="vipbody">
<div class="mrbox2" > 
<h3><span>فرم پرداخت اشتراک VIP</span></h3>';
		if(isset($_SESSION['ErrorInput']))
		{
			echo $_SESSION['ErrorInput'];
			unset($_SESSION['ErrorInput']);
		}
		
        echo '<br />
        <form name="frm1" method="post">
          <label class="title"> تصویر امنیتی</label>
          <input type="text" id="captcha" min="100" max="100000" name="captcha" class="CapchaInput" />
          <div class="captchalogin" style="background-color:#2064af;text-align: center;color: #FFFFFF;font-weight: bold;">'.$rand.'</div>
          <br />
          <br />
          <p align="center"><font color="#0066FF">برای ورود به درگاه پرداخت روی کلید زیر کلیک کنید.</font></p>
          <input type="submit" name="submit" id="submit" value="&nbsp;" class="dlbtn"/>
          <br />
        </form>
</div>
</body>
</html>';
         
	}
	
	public static function vip_listener() {
		 $SiteURL=get_option('siteurl');
         if(get_query_var("buy_account_vip")==NULL) {
			if(get_query_var("vipdownload")==NULL)
			{
				if (get_query_var("vip_action") == "ipnvip") {
					self::ipnvip();
					exit();
				}
			} else{
								
				$idproduct = intval($_GET["vipdownload"]);
				global $wpdb;
				$table_name = $wpdb->prefix . "pfd_products";
				$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d",$idproduct), ARRAY_A, 0);
				
				// update extant_daily
				
				$current_user=wp_get_current_user();
				$user_id=$current_user->ID;
				$Tusermeta = $wpdb->prefix . "usermeta";
				
				$Exp_Vip=get_user_meta($user_id,'exp_vip',true);
				$Exp_Per_Day=intval(get_user_meta($user_id,'exp_per_day',true));
				$Extant_Daily=intval(get_user_meta($user_id,'extant_daily',true));
			
				
				if(get_exp_day($Exp_Vip,true))
				{
					$html = '<html><head><link rel="stylesheet"  media="all" type="text/css" href="'.plugins_url('style.css',__FILE__).'"></head><body class="vipbody" >';
					
					$html .='<div class="mrbox2">';
					$html .= '<b>اشتراک ویژه شما به پایان رسیده <br> شما اجازه مشاهده این بخش را ندارید</b><br>شما می توانید اشتراک جدید بخرید<br>';
					$html=CreateFormBuyVIP1($html);
					$html .='</div></body></html>';
					echo $html;
					die();
				}
				else
				{
					// بررسی تعداد دانلود مجاز و باقی مانده
					if(($Extant_Daily==-1 or ($Extant_Daily>=1)) and ($Extant_Daily<=$Exp_Per_Day))
					{
						// Extant_Daily-1
						$x=$Extant_Daily;
						
						if($Exp_Per_Day>=0)
						{
							$x=$Extant_Daily-1;
						}						
						update_user_meta($user_id,'extant_daily',$x);
													
						// force download
						header('Content-disposition: attachment; filename=' . basename($product["file"]));
						header('Content-Type: application/octet-stream');
						header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
						header('Expires: 0');
						$result = wp_remote_get($product["file"]);
						echo $result['body'];
						die();
						
					}
					else
					{
						$html ='<html><head>
						<link media="all" rel="stylesheet" type="text/css" href="'.plugins_url('style.css',__FILE__).'"></head><body class="vipbody"><div class="mrbox2">';
						$html .= '<b>تعداد دانلود روزانه شما به پایان رسیده</b><br>
					شما می توانید فردا مراجعه کنید<br>';
						$html .='
						<hr>
						<b> همین الان محصول را با پرداخت آنلاین بخرید </b>
						<br><br>
						<form name="frm_nextpay'.$product['id']. '" action="'.$SiteURL.'" method="get">
						<input type="hidden" name="checkout" value="'.$product['id'].'">
						<input type="submit" name="submit" value="پرداخت آنلاین و دانلود" class="mrbtn_red" ></form>';
						
						$html .='</div></body></html>';			
						echo $html;
						die();
					}
				}
				
			}
		} else { // --->buy_account_vip
			@session_start();
			if(isset($_POST['submit']))
			{
				$_captcha=$_SESSION['captcha'];
				
				if($_POST['captcha']!=$_captcha)
				{
					$_SESSION['ErrorInput']='<ErrorMsg>تصویر امنیتی را درست وارد نمایید</ErrorMsg>';
					self::get_captcha();
					exit();
				}
				else
				{
					$account_id = get_query_var("buy_account_vip");
		
					global $wpdb;
					$current_user = wp_get_current_user();
				    $user_id=$current_user->ID;
					
					$account_name = $wpdb->prefix . "vip_accounts";
		
					// get account
					$account = $wpdb->get_row($wpdb->prepare("SELECT * FROM $account_name WHERE id = %d",$account_id) , ARRAY_A, 0);
		
					// construct order
					$order_name = $wpdb->prefix . "vip_orders";


                    $client = new SoapClient('http://api.nextpay.org/gateway/token.wsdl', array('encoding' => 'UTF-8'));
                    $api_key = get_option('vip_nextpay_api');
                    $amount = $account["cost"] / 10;
                    $order_id = md5(uniqid(rand(), true));
                    $redirect = get_option('siteurl') . "/?vip_action=ipnvip";

                    $result = $client->TokenGenerator(
                        array(
                            'api_key' 	=> $api_key,
                            'order_id'	=> $order_id,
                            'amount' 		=> $amount,
                            'callback_uri' 	=> $redirect
                        )
                    );

                    $result = $result->TokenGeneratorResult;

                    if(intval($result->code) == -1){
						$go = "https://api.nextpay.org/gateway/payment/" . $result->trans_id;
						$wpdb->insert( $order_name, array('idaccount' => $account_id,'iduser' => $user_id, 'order_code' => $order_id, 'fulfilled' => 0, 'created_at' => time(), 'cost' => $account["cost"]), array( '%d','%d', '%s', '%d', '%d', '%s') );
						header("Location: $go");
					}
					else
					{
						
						$html ='<html><head>
						<link media="all" rel="stylesheet" type="text/css" href="'.plugins_url('style.css',__FILE__).'"></head><body class="vipbody"><div class="mrbox2">';
						$html .='<b>در برقراري ارتباط با درگاه پرداخت nextpay مشکلي بوجود آمده است<br>';
						$html .='لطفا به مدیر اطلاع دهید<br>کد خطا : '.$result->oode.'</b><br><br>';
						$html .='<a class="mrbtn_green" href="'.get_option('siteurl').'">بازگشت به صفحه اصلي</a>';
						$html .='</div></body></html>';			
						echo $html;
						exit();
						
					}
				}
			}
			else{
				self::get_captcha();
				exit();
			}			
		}
    }	
	
	
	
	
	
	
	public static function add_linkdownload_vip_form() {
		
echo "<script type=\"text/javascript\">
		function insert_vip_linkdownload(){
			product_id = jQuery(\"#vip_product_selector\").val();
			construct='[vip_linkdownload idproduct='+product_id+']';
			
			var wdw = window.dialogArguments || opener || parent || top;
			wdw.send_to_editor(construct);
		}
</script>";
echo '<div id="vip_linkdownload_form" style="display:none;">
  <div class="wrap" style="text-align:right; direction:rtl;">
    <div>
      
        <div style="padding:15px 15px 0 15px;">
			<h3 style="font-size:16pt">قرارد دادن لينک پرداخت nextpay و نمایش لینک دانلود کاربران vip</h3>
			<span>لطفا محصول مورد نظرتان را از لينک زير انتخاب نماييد</span>
		</div>
        
      <div style="padding:15px 15px 0 15px;">
        <table width="100%">
          <tr>
            <td width="150"><strong>محصول</strong></td>
            <td>';
                
				global $wpdb;
				$table_name = $wpdb->prefix . "pfd_products";
				$products = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id ASC;" ,ARRAY_A);
				if (count($products) == 0) 
				{
					echo 'محصولي وجود ندارد. <a href="'.get_option('siteurl').'/wp-admin/admin.php?page=nextpay-file-download-products'.'">نوشته خود را ذخيره کنيد و سپس اينجا کليک نماييد.</a>';
				} 
				else
				{
					echo '<select id="vip_product_selector" style="width:300px;">';
					foreach($products as $product) {
						echo '<option value="'.$product["id"].'">'.$product["name"].' ('.$product["cost"].' ريال)</option>';
					}
					echo '</select>';
				}
			  	
              echo '</td>
          </tr>
        </table>
      </div>
      <div style="padding:15px;">
        <input type="button" class="button-primary" value="نمایش در نوشته" onClick="insert_vip_linkdownload();"/>
        &nbsp;&nbsp;
        <input type="button" class="button" value="بستن" onClick="tb_remove();"/>
        </div>
    </div>
  </div>
</div>';
	}
	
	
}