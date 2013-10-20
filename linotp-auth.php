<?php
/*
Plugin Name: LinOTP authentication
Plugin URI: http://github/cornelinux/wp-linotp-auth
Description: Used to externally authenticate WP users with one time passwords against LinOTP.
Version: 1.0
Author: Cornelius Kölbel

    Copyright 2013 Cornelius Kölbel (corny@cornelinux.de)

    This program is free software; you can redistribute it and/or modify
    it  under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/



class LinOTP {
        private $server, $verify_peer, $verify_host;

        public function __construct( $server = "localhost",  $verify_peer=0, $verify_host=0) {
                $this->server=$server;
                # can be 0 or 2
                #$verify_host = 0;
                #$verify_peer = 0;
                $this->verify_peer=$verify_peer;
                $this->verify_host=$verify_host;
        }


        public function linotp_auth($user="", $pass="", $realm="") {
                $ret=false;
                try {
                        $server = $this->server;
                        $REQUEST="https://$server/validate/check?user=$user&pass=$pass";
                        if(""!=$realm)
                                $REQUEST="$REQUEST&realm=$realm";
#                               print "\n\n\n$REQUEST\n\n\n";


                        if(!function_exists("curl_init"))
                                die("PHP cURL extension is not installed");

                        $ch=curl_init($REQUEST);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_peer);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verify_host);
                        $r=curl_exec($ch);
                        curl_close($ch);


                        $jObject = json_decode($r);
                        if (true == $jObject->{'result'}->{'status'} )
                                if (true == $jObject->{'result'}->{'value'} )
                                        $ret=true;
                } catch (Exception $e) {
			error_log("Error in receiving response from LinOTP server: $e");
                }
                return $ret;
        }
}

function linotp_auth_activate(){
	add_option('linotp_server',"","The FQDN of the LinOTP server. This server must be reached via https.");
	add_option('linotp_verify_host',0,"Wether the hostname of the certificate shall be verified (0 or 2)");
	add_option('linotp_verify_peer',0,"Wether the certificate shall be verified (0 or 2)");
	add_option('linotp_realm',"","The Realm in the LinOTP server. Leave empty if you want to use the default realm.");
}


function linotp_auth_init(){
	register_setting('linotp_auth','linotp_server');
	register_setting('linotp_auth','linotp_verify_host');
	register_setting('linotp_auth','linotp_verify_peer');
	register_setting('linotp_auth','linotp_realm');
}

//page for config menu
function linotp_auth_add_menu() {
	add_options_page("LinOTP settings", "LinOTP settings", 10, __FILE__,"linotp_auth_display_options");
}

//actual configuration screen
function linotp_auth_display_options() { 
?>
	<div class="wrap">
	<h2>LinOTP Authentication</h2>        
	<form method="post" action="options.php">
	<?php settings_fields('linotp_auth'); ?>
        <h3>LinOTP Settings</h3>
          <strong>Make sure your admin accounts also exist in the LinOTP server.</strong>
        <table class="form-table">
        <tr valign="top">
            <th scope="row"><label>LinOTP Server name</label></th>
				<td><input type="text" name="linotp_server" value="<?php echo get_option('linotp_server'); ?>" /> </td>
				<td><span class="description"><strong style="color:red;">required</strong>The FQDN of the LinOTP server.</span></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label>Realm</label></th>
				<td><input type="text" name="linotp_realm" value="<?php echo get_option('linotp_realm'); ?>" /> </td>
				<td><span class="description">The realm of the user in the LinOTP server. Leave empty if you use default realm.</span> </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label>Verify Host</label></th>
				<td><input type="text" name="linotp_verify_host" value="<?php echo get_option('linotp_verify_host'); ?>" /> </td>
				<td><span class="description">Verify SSL hostname. (0 or 2)</span></td>
        </tr>        
        <tr valign="top">
            <th scope="row"><label>Verify Peer</label></th>
				<td><input type="text" name="linotp_verify_peer" value="<?php echo get_option('linotp_verify_peer'); ?>" /> </td>
				<td><span class="description">Verify SSL certificate. (0 or 2)</span></td>
        </tr>        
        </table>	
	<p class="submit">
	<input type="submit" name="Submit" value="Save changes" />
	</p>
	</form>
	</div>
<?php
}


if ( !function_exists("wp_authenticate") ) :
function wp_authenticate($username,$password) {
	$username = sanitize_user($username);
        $password = trim($password);
	$user = null;

	//get the server name
	$server = get_option('linotp_server');

	// get SSL options
        $verify_peer = get_option('linotp_verify_peer');
        $verify_host = get_option('linotp_verify_host');
        $realm = get_option('linotp_realm');

        $l = new LinOTP( $server, $verify_peer, $verify_host );
        $r = $l->linotp_auth($username, $password, $realm);

	if ($r) {
		$user = new WP_User( $username );
	}


        if ( $user == null ) {
                // TODO what should the error message be? (Or would these even happen?)
                // Only needed if all authentication handlers fail to return anything.
                $user = new WP_Error('authentication_failed', __('<strong>ERROR</strong>: Invalid username or incorrect password.'));
        }

        $ignore_codes = array('empty_username', 'empty_password');

        if (is_wp_error($user) && !in_array($user->get_error_code(), $ignore_codes) ) {
                do_action('wp_login_failed', $username);
        }

	// Fallback
	// return new WP_User($username);
        return $user;
}
endif;

add_action('admin_init', 'linotp_auth_init' );
add_action('admin_menu', 'linotp_auth_add_menu');

register_activation_hook( __FILE__, 'linotp_auth_activate' );
