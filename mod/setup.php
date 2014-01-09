<?php

$install_wizard_pass=1;


function setup_init(&$a){

	// Ensure that if somebody hasn't read the install documentation and doesn't have all 
	// the required modules or has a totally borked shared hosting provider and they can't 
	// figure out what the hell is going on - that we at least spit out an error message which
	// we can inquire about when they write to tell us that our software doesn't work.

	// The worst thing we can do at this point is throw a white screen of death and rely on 
	// them knowing about servers and php modules and logfiles enough so that we can guess 
	// at the source of the problem. As ugly as it may be, we need to throw a technically worded
	// PHP error message in their face. Once installation is complete application errors will 
	// throw a white screen because these error messages divulge information which can 
	// potentially be useful to hackers.       
	
	
	error_reporting(E_ERROR | E_WARNING | E_PARSE ); 
	ini_set('log_errors','0'); 
	ini_set('display_errors', '1');


	// $baseurl/setup/testrwrite to test if rewite in .htaccess is working
	if (argc() ==2  && argv(1)=="testrewrite") {
		echo "ok";
		killme();
	}
	global $install_wizard_pass;
	if (x($_POST,'pass'))
		$install_wizard_pass = intval($_POST['pass']);


}

function setup_post(&$a) {
	global $install_wizard_pass, $db;

	switch($install_wizard_pass) {
		case 1:
		case 2:
			return;
			break; // just in case return don't return :)
		case 3:
			$urlpath = $a->get_path();
			$dbhost = notags(trim($_POST['dbhost']));
			$dbport = intval(notags(trim($_POST['dbport'])));
			$dbuser = notags(trim($_POST['dbuser']));
			$dbpass = notags(trim($_POST['dbpass']));
			$dbdata = notags(trim($_POST['dbdata']));
			$phpath = notags(trim($_POST['phpath']));
			$adminmail = notags(trim($_POST['adminmail']));
			$siteurl = notags(trim($_POST['siteurl']));

			require_once('include/dba/dba_driver.php');
			unset($db);
			$db = dba_factory($dbhost, $dbport, $dbuser, $dbpass, $dbdata, true);
			if(! $db->connected) {
				echo "Database Connect failed: " . $db->error;
				killme();
			}
			/*if(get_db_errno()) {
				unset($db);
				$db = dba_factory($dbhost, $dbport, $dbuser, $dbpass, '', true);

				if(! get_db_errno()) {
					$r = q("CREATE DATABASE '%s'",
							dbesc($dbdata)
					);
					if($r) {
						unset($db);
						$db = new dba($dbhost, $dbport, $dbuser, $dbpass, $dbdata, true);
					} else {
						$a->data['db_create_failed']=true;
					}
				} else {
					$a->data['db_conn_failed']=true;
					return;
				}
			}*/
			if(get_db_errno()) {
				$a->data['db_conn_failed']=true;
			}

			return; 
			break;
		case 4:
			$urlpath = $a->get_path();
			$dbhost = notags(trim($_POST['dbhost']));
			$dbport = intval(notags(trim($_POST['dbport'])));
			$dbuser = notags(trim($_POST['dbuser']));
			$dbpass = notags(trim($_POST['dbpass']));
			$dbdata = notags(trim($_POST['dbdata']));
			$phpath = notags(trim($_POST['phpath']));
			$timezone = notags(trim($_POST['timezone']));
			$adminmail = notags(trim($_POST['adminmail']));
			$siteurl = notags(trim($_POST['siteurl']));
			

			if($siteurl != z_root()) {
		        $test = z_fetch_url($siteurl."/setup/testrewrite");
				if((! $test['success']) || ($test['body'] != 'ok'))  {
					$a->data['url_fail'] = true;
					return;
				}
			}

			// connect to db
			$db = dba_factory($dbhost, $dbport, $dbuser, $dbpass, $dbdata, true);

			if(! $db->connected) {
				echo 'CRITICAL: DB not connected.';
				killme();
			}

			$tpl = get_intltext_template('htconfig.tpl');
			$txt = replace_macros($tpl,array(
				'$dbhost' => $dbhost,
				'$dbport' => $dbport,
				'$dbuser' => $dbuser,
				'$dbpass' => $dbpass,
				'$dbdata' => $dbdata,
				'$timezone' => $timezone,
				'$siteurl' => $siteurl,
				'$site_id' => random_string(),
				'$phpath' => $phpath,
				'$adminmail' => $adminmail
			));

			$result = file_put_contents('.htconfig.php', $txt);
			if(! $result) {
				$a->data['txt'] = $txt;
			}

			$errors = load_database($db);

			if($errors)
				$a->data['db_failed'] = $errors;
			else
				$a->data['db_installed'] = true;

			return;
		break;
	}
}

function get_db_errno() {
	if(class_exists('mysqli'))
		return mysqli_connect_errno();
	else
		return mysql_errno();
}		

function setup_content(&$a) {

	global $install_wizard_pass, $db;
	$o = '';
	$wizard_status = "";
	$install_title = t('Red Matrix Server - Setup');
	

	
	if(x($a->data,'db_conn_failed')) {
		$install_wizard_pass = 2;
		$wizard_status =  t('Could not connect to database.');
	}
	if(x($a->data,'url_fail')) {
		$install_wizard_pass = 3;
		$wizard_status =  t('Could not connect to specified site URL. Possible SSL certificate or DNS issue.');
	}

	if(x($a->data,'db_create_failed')) {
		$install_wizard_pass = 2;
		$wizard_status =  t('Could not create table.');
	}
	
	$db_return_text="";
	if(x($a->data,'db_installed')) {
		$txt = '<p style="font-size: 130%;">';
		$txt .= t('Your site database has been installed.') . EOL;
		$db_return_text .= $txt;
	}

	if(x($a->data,'db_failed')) {
		$txt = t('You may need to import the file "install/database.sql" manually using phpmyadmin or mysql.') . EOL;
		$txt .= t('Please see the file "install/INSTALL.txt".') . EOL ."<hr>" ;
		$txt .= "<pre>".$a->data['db_failed'] . "</pre>". EOL ;
		$db_return_text .= $txt;
	}
	
	if($db && $db->connected) {
		$r = q("SELECT COUNT(*) as `total` FROM `account`");
		if($r && count($r) && $r[0]['total']) {
			$tpl = get_markup_template('install.tpl');
			return replace_macros($tpl, array(
				'$title' => $install_title,
				'$pass' => '',
				'$status' => t('Permission denied.'),
				'$text' => '',
			));
		}
	}

	if(x($a->data,'txt') && strlen($a->data['txt'])) {
		$db_return_text .= manual_config($a);
	}
	
	if ($db_return_text!="") {
		$tpl = get_markup_template('install.tpl');
		return replace_macros($tpl, array(
			'$title' => $install_title,
			'$pass' => "",
			'$text' => $db_return_text . what_next(),
		));
	}
	
	switch ($install_wizard_pass){
		case 1: { // System check


			$checks = array();

			check_funcs($checks);

			check_htconfig($checks);

			check_smarty3($checks);

			check_store($checks);

			check_keys($checks);
			
			if(x($_POST,'phpath'))
				$phpath = notags(trim($_POST['phpath']));

			check_php($phpath, $checks);

            check_htaccess($checks);
            
			function check_passed($v, $c){
				if ($c['required'])
					$v = $v && $c['status'];
				return $v;
			}
			$checkspassed = array_reduce($checks, "check_passed", true);
	        


			$tpl = get_markup_template('install_checks.tpl');
			$o .= replace_macros($tpl, array(
				'$title' => $install_title,
				'$pass' => t('System check'),
				'$checks' => $checks,
				'$passed' => $checkspassed,
				'$see_install' => t('Please see the file "install/INSTALL.txt".'),
				'$next' => t('Next'),
				'$reload' => t('Check again'),
				'$phpath' => $phpath,
				'$baseurl' => $a->get_baseurl(),
			));
			return $o;
		}; break;
		
		case 2: { // Database config

			$dbhost = ((x($_POST,'dbhost')) ? notags(trim($_POST['dbhost'])) : 'localhost');
			$dbuser = notags(trim($_POST['dbuser']));
			$dbport = intval(notags(trim($_POST['dbport'])));
			$dbpass = notags(trim($_POST['dbpass']));
			$dbdata = notags(trim($_POST['dbdata']));
			$phpath = notags(trim($_POST['phpath']));
			$adminmail = notags(trim($_POST['adminmail']));
			$siteurl = notags(trim($_POST['siteurl']));
			

			$tpl = get_markup_template('install_db.tpl');
			$o .= replace_macros($tpl, array(
				'$title' => $install_title,
				'$pass' => t('Database connection'),
				'$info_01' => t('In order to install Red Matrix we need to know how to connect to your database.'),
				'$info_02' => t('Please contact your hosting provider or site administrator if you have questions about these settings.'),
				'$info_03' => t('The database you specify below should already exist. If it does not, please create it before continuing.'),

				'$status' => $wizard_status,
				
				'$dbhost' => array('dbhost', t('Database Server Name'), $dbhost, t('Default is localhost')),
				'$dbport' => array('dbport', t('Database Port'), $dbport, t('Communication port number - use 0 for default')),
				'$dbuser' => array('dbuser', t('Database Login Name'), $dbuser, ''),
				'$dbpass' => array('dbpass', t('Database Login Password'), $dbpass, ''),
				'$dbdata' => array('dbdata', t('Database Name'), $dbdata, ''),

				'$adminmail' => array('adminmail', t('Site administrator email address'), $adminmail, t('Your account email address must match this in order to use the web admin panel.')),
				'$siteurl' => array('siteurl', t('Website URL'), z_root(), t('Please use SSL (https) URL if available.')),
				

				'$lbl_10' => t('Please select a default timezone for your website'),
				
				'$baseurl' => $a->get_baseurl(),
				
				'$phpath' => $phpath,
				
				'$submit' => t('Submit'),
				
			));
			return $o;
		}; break;
		case 3: { // Site settings
			require_once('include/datetime.php');
			$dbhost = ((x($_POST,'dbhost')) ? notags(trim($_POST['dbhost'])) : 'localhost');
			$dbport = intval(notags(trim($_POST['dbuser'])));
			$dbuser = notags(trim($_POST['dbuser']));
			$dbpass = notags(trim($_POST['dbpass']));
			$dbdata = notags(trim($_POST['dbdata']));
			$phpath = notags(trim($_POST['phpath']));
			
			$adminmail = notags(trim($_POST['adminmail']));
			$siteurl = notags(trim($_POST['siteurl']));
			$timezone = ((x($_POST,'timezone')) ? ($_POST['timezone']) : 'America/Los_Angeles');
			
			$tpl = get_markup_template('install_settings.tpl');
			$o .= replace_macros($tpl, array(
				'$title' => $install_title,
				'$pass' => t('Site settings'),

				'$status' => $wizard_status,
				
				'$dbhost' => $dbhost, 
				'$dbport' => $dbport, 
				'$dbuser' => $dbuser,
				'$dbpass' => $dbpass,
				'$dbdata' => $dbdata,
				'$phpath' => $phpath,
				
				'$adminmail' => array('adminmail', t('Site administrator email address'), $adminmail, t('Your account email address must match this in order to use the web admin panel.')),

				'$siteurl' => array('siteurl', t('Website URL'), z_root(), t('Please use SSL (https) URL if available.')),

				
				'$timezone' => field_timezone('timezone', t('Please select a default timezone for your website'), $timezone, ''),
				
				'$baseurl' => $a->get_baseurl(),
				
				
				
				'$submit' => t('Submit'),
				
			));
			return $o;
		}; break;
			
	}
}

/**
 * checks   : array passed to template
 * title    : string
 * status   : boolean
 * required : boolean
 * help		: string optional
 */
function check_add(&$checks, $title, $status, $required, $help){
	$checks[] = array(
		'title' => $title,
		'status' => $status,
		'required' => $required,
		'help'	=> $help,
	);
}

function check_php(&$phpath, &$checks) {
	if (strlen($phpath)){
		$passed = file_exists($phpath);
	} else {
		$phpath = trim(shell_exec('which php'));
		$passed = strlen($phpath);
	}
	$help = "";
	if(!$passed) {
		$help .= t('Could not find a command line version of PHP in the web server PATH.'). EOL;
		$help .= t("If you don't have a command line version of PHP installed on server, you will not be able to run background polling via cron.") . EOL;
		$help .= EOL . EOL ;
		$tpl = get_markup_template('field_input.tpl');
		$help .= replace_macros($tpl, array(
			'$field' => array('phpath', t('PHP executable path'), $phpath, t('Enter full path to php executable. You can leave this blank to continue the installation.')),
		));
		$phpath="";
	}
	
	check_add($checks, t('Command line PHP').($passed?" (<tt>$phpath</tt>)":""), $passed, false, $help);
	
	if($passed) {
		$str = autoname(8);
		$cmd = "$phpath install/testargs.php $str";
		$result = trim(shell_exec($cmd));
		$passed2 = $result == $str;
		$help = "";
		if(!$passed2) {
			$help .= t('The command line version of PHP on your system does not have "register_argc_argv" enabled.'). EOL;
			$help .= t('This is required for message delivery to work.');
		}
		check_add($checks, t('PHP register_argc_argv'), $passed, true, $help);
	}
	

}

function check_keys(&$checks) {

	$help = '';

	$res = false;

	if(function_exists('openssl_pkey_new')) 
		$res=openssl_pkey_new(array(
		'digest_alg' => 'sha1',
		'private_key_bits' => 4096,
		'encrypt_key' => false ));

	// Get private key

	if(! $res) {
		$help .= t('Error: the "openssl_pkey_new" function on this system is not able to generate encryption keys'). EOL;
		$help .= t('If running under Windows, please see "http://www.php.net/manual/en/openssl.installation.php".');
	}
	check_add($checks, t('Generate encryption keys'), $res, true, $help);

}


function check_funcs(&$checks) {
	$ck_funcs = array();
	check_add($ck_funcs, t('libCurl PHP module'), true, true, "");
	check_add($ck_funcs, t('GD graphics PHP module'), true, true, "");
	check_add($ck_funcs, t('OpenSSL PHP module'), true, true, "");
	check_add($ck_funcs, t('mysqli PHP module'), true, true, "");
	check_add($ck_funcs, t('mb_string PHP module'), true, true, "");
	check_add($ck_funcs, t('mcrypt PHP module'), true, true, "");
		
	
	if(function_exists('apache_get_modules')){
		if (! in_array('mod_rewrite',apache_get_modules())) {
			check_add($ck_funcs, t('Apache mod_rewrite module'), false, true, t('Error: Apache webserver mod-rewrite module is required but not installed.'));
		} else {
			check_add($ck_funcs, t('Apache mod_rewrite module'), true, true, "");
		}
	}
	if((! function_exists('proc_open')) || strstr(ini_get('disable_functions'),'proc_open')) {
		check_add($ck_funcs, t('proc_open'), false, true, t('Error: proc_open is required but is either not installed or has been disabled in php.ini'));
	}
	else {
		check_add($ck_funcs, t('proc_open'), true, true, "");
	}

	if(! function_exists('curl_init')){
		$ck_funcs[0]['status']= false;
		$ck_funcs[0]['help']= t('Error: libCURL PHP module required but not installed.');
	}
	if(! function_exists('imagecreatefromjpeg')){
		$ck_funcs[1]['status']= false;
		$ck_funcs[1]['help']= t('Error: GD graphics PHP module with JPEG support required but not installed.');
	}
	if(! function_exists('openssl_public_encrypt')) {
		$ck_funcs[2]['status']= false;
		$ck_funcs[2]['help']= t('Error: openssl PHP module required but not installed.');
	}
	if(! function_exists('mysqli_connect')){
		$ck_funcs[3]['status']= false;
		$ck_funcs[3]['help']= t('Error: mysqli PHP module required but not installed.');
	}
	if(! function_exists('mb_strlen')){
		$ck_funcs[4]['status']= false;
		$ck_funcs[4]['help']= t('Error: mb_string PHP module required but not installed.');
	}
	if(! function_exists('mcrypt_encrypt')){
		$ck_funcs[5]['status']= false;
		$ck_funcs[5]['help']= t('Error: mcrypt PHP module required but not installed.');
	}
	
	$checks = array_merge($checks, $ck_funcs);
	

}


function check_htconfig(&$checks) {
	$status = true;
	$help = "";
	if(	(file_exists('.htconfig.php') && !is_writable('.htconfig.php')) ||
		(!file_exists('.htconfig.php') && !is_writable('.')) ) {
	
		$status=false;
		$help = t('The web installer needs to be able to create a file called ".htconfig.php" in the top folder of your web server and it is unable to do so.') .EOL;
		$help .= t('This is most often a permission setting, as the web server may not be able to write files in your folder - even if you can.').EOL;
		$help .= t('At the end of this procedure, we will give you a text to save in a file named .htconfig.php in your Red top folder.').EOL;
		$help .= t('You can alternatively skip this procedure and perform a manual installation. Please see the file "install/INSTALL.txt" for instructions.').EOL; 
	}
    
	check_add($checks, t('.htconfig.php is writable'), $status, false, $help);

}

function check_smarty3(&$checks) {
	$status = true;
	$help = "";
	if(	!is_writable('view/tpl/smarty3') ) {
	
		$status=false;
		$help = t('Red uses the Smarty3 template engine to render its web views. Smarty3 compiles templates to PHP to speed up rendering.') .EOL;
		$help .= t('In order to store these compiled templates, the web server needs to have write access to the directory view/tpl/smarty3/ under the Red top level folder.').EOL;
		$help .= t('Please ensure that the user that your web server runs as (e.g. www-data) has write access to this folder.').EOL;
		$help .= t('Note: as a security measure, you should give the web server write access to view/tpl/smarty3/ only--not the template files (.tpl) that it contains.').EOL; 
	}
    
	check_add($checks, t('view/tpl/smarty3 is writable'), $status, true, $help);

}

function check_store(&$checks) {
	$status = true;
	$help = "";

	@mkdir('store',STORAGE_DEFAULT_PERMISSIONS);

	if(	!is_writable('store') ) {
	
		$status=false;
		$help = t('Red uses the store directory to save uploaded files. The web server needs to have write access to the store directory under the Red top level folder') . EOL;
		$help .= t('Please ensure that the user that your web server runs as (e.g. www-data) has write access to this folder.').EOL;
	}
    
	check_add($checks, t('store is writable'), $status, true, $help);

}


function check_htaccess(&$checks) {
	$a = get_app();
	$status = true;
	$help = "";
	if (function_exists('curl_init')){
        $test = z_fetch_url($a->get_baseurl()."/setup/testrewrite");
		if(! $test['success']) {
			if(strstr($a->get_baseurl(),'https://')) {
				$test = z_fetch_url($a->get_baseurl() . "/setup/testrewrite",false,0,array('novalidate' => true));
				if($test['success']) {
					check_add($checks, t('SSL certificate validation'),false,true, t('SSL certificate cannot be validated. Fix certificate or disable https access to this site.'));
				}
			}
		}		

        if ((! $test['success']) || ($test['body'] != "ok")) {
            $status = false;
            $help = t('Url rewrite in .htaccess is not working. Check your server configuration.');
        }
        check_add($checks, t('Url rewrite is working'), $status, true, $help); 
    } else {
        // cannot check modrewrite if libcurl is not installed
    }
	
}

	
function manual_config(&$a) {
	$data = htmlspecialchars($a->data['txt'],ENT_COMPAT,'UTF-8');
	$o = t('The database configuration file ".htconfig.php" could not be written. Please use the enclosed text to create a configuration file in your web server root.');
	$o .= "<textarea rows=\"24\" cols=\"80\" >$data</textarea>";
	return $o;
}

function load_database_rem($v, $i){
	$l = trim($i);
	if (strlen($l)>1 && ($l[0]=="-" || ($l[0]=="/" && $l[1]=="*"))){
		return $v;
	} else  {
		return $v."\n".$i;
	}
}


function load_database($db) {

	$str = file_get_contents('install/database.sql');
	$arr = explode(';',$str);
	$errors = false;
	foreach($arr as $a) {
		if(strlen(trim($a))) {	
			$r = @$db->q(trim($a));
			if(! $r) {
				$errors .=  t('Errors encountered creating database tables.') . $a . EOL;
			}
		}
	}
	return $errors;
}

function what_next() {
	$a = get_app();
	// install the standard theme
	set_config('system','allowed_themes','redbasic');
	$baseurl = $a->get_baseurl();
	return 
		t('<h1>What next</h1>')
		."<p>".t('IMPORTANT: You will need to [manually] setup a scheduled task for the poller.')
		.t('Please see the file "install/INSTALL.txt".')			
		."</p><p>"
		.t("Go to your new Red node <a href='$baseurl/register'>registration page</a> and register as new user. Remember to use the same email you have entered as administrator email. This will allow you to enter the site admin panel.")
		."</p>";
}


