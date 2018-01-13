<?php

	// site config
	$siteBaseUri = Site::getConfig()->wwwroot;
	$siteDomain = parse_url($siteBaseUri)['host'];
	$pilotBaseUri = $siteBaseUri . 'pilot2/';

	// FB OAuth configs
	$fbConfig = [
	  'app_id' => '248990911853204', // Replace {app-id} with your app id
	  'app_secret' => 'd6f8aa2d88bfe629a32a2264dcc22872',
	  'default_graph_version' => 'v2.10',
	];

	// twitter OAuth configs
	$consumerKey = 'b6LpAvUyGl3B3kAUzadgyVFh0';
	$consumerSecret = 'nY2zs0tgR5YynyWuXCDjE7CxRPCUzqeUlYCza7nqCx5cpv17aA';
	$redirectURL = $pilotBaseUri;

	// youtube OAuth configs
	$google_url = "https://accounts.google.com/o/oauth2/auth";
	$google_params = [
		"response_type" => "code",
		"client_id" => "898715087003-k4rte6s993e759r875gs24mctv3j6ttl.apps.googleusercontent.com",
		"redirect_uri" => $pilotBaseUri,
		"scope" => "https://www.googleapis.com/auth/plus.me"
	];

	if(!session_id()) {
		session_start();
	}
 	$video = CloudVideoPeer::getByID($vars['object']->id);
	$linkVideoss =$video->update(array('opensharing' => 1));
	$linkVideo =  $video->getShareURL('naveen@parangat.com');
	
	$downloadvideo = $video->getVideoSourceLq();
	// echo $downloadvideoss = file_put_contents("swws.mp4", fopen("https://d2ddoozxak4dq6.cloudfront.net/user-dat/12/6ea8cf7bebb4f4ce6aadf4ab7f286491.dat.lq?AWSAccessKeyId=AKIAIZVH4RMOI6TEYJSA&Expires=1513887241&Signature=1Z%2FEf1l5Ii9VIvtP6nnB92x3i2o%3D", 'r'));
	
	$embeddable = $vars['object']->embeddable;
	$thumbnail = $vars['object']->getThumbnail();
	$src       = Site::getConfig()->wwwroot . "-/video/{$vars['object']->id}/retrieve/video.mp4";
	$fallback  = '<video poster="' . $thumbnail . '" width="598" height="336" controls="controls" src="' . $src . '" type="video/mp4" ><img src="' . $thumbnail . '" width="598" height="336" /></video>';
	$embedCode = '<iframe id="_player' . $vars['object']->id . '" name="_player' . $vars['object']->id . '" src="' . Site::getConfig()->wwwroot . '-/videoembed/' . $vars['object']->id . '/" scrolling="no" marginwidth="0" marginheight="0" frameborder="0" vspace="0" hspace="0" width="598" height="336">' . $fallback . '</iframe>';
	
	//Include Facebook Classes
	include_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/external/Facebook/autoload.php');
	use Facebook;
	use Facebook\FileUpload\FacebookFile;

	$fb = new Facebook\Facebook($fbConfig);
	 $helper = $fb->getRedirectLoginHelper();

	$permissions = ['public_profile,email,user_videos,publish_actions']; // Optional permissions
	$loginUrl = $helper->getLoginUrl($siteBaseUri, $permissions);
	// echo $_SESSION['fb_access_token'];
	
    
	//Include Twitter config file && User class
	include_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/external/twitter_sdk/twConfig.php');
	
	//Include Instagram class
	include_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/external/instagram/instagram-api-settings.php');
	
	//If OAuth token not matched
	if(isset($_REQUEST['oauth_token']) && $_SESSION['token'] !== $_REQUEST['oauth_token']){
		//Remove token from session
		unset($_SESSION['token']);
		unset($_SESSION['token_secret']);
	}

	//If user already verified 
	if(isset($_SESSION['status']) && $_SESSION['status'] == 'verified' && !empty($_SESSION['request_vars'])){
		//Retrive variables from session
		$username 		  = $_SESSION['request_vars']['screen_name'];
		$twitterId		  = $_SESSION['request_vars']['user_id'];
		$oauthToken 	  = $_SESSION['request_vars']['oauth_token'];
		$oauthTokenSecret = $_SESSION['request_vars']['oauth_token_secret'];
		$profilePicture	  = $_SESSION['userData']['picture'];
		
		/*
		 * Prepare output to show to the user
		 */
		$twClient = new TwitterOAuth($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret);
		
		//If user submits a tweet to post to twitter
		if(isset($_POST["updateme"])){
			$my_update = $twClient->post('statuses/update', array('status' => $_POST["updateme"]));
		}
		
		//Display username and logout link
		$output = '<div class="welcome_txt">Welcome <strong>'.$username.'</strong> (Twitter ID : '.$twitterId.'). <a href="logout.php">Logout</a>!</div>';
		
		//Display profile iamge and tweet form
		$output .= '<div class="tweet_box">';
		$output .= '<img src="'.$profilePicture.'" width="120" height="110"/>';
		$output .= '<form method="post" action=""><table width="200" border="0" cellpadding="3">';
		$output .= '<tr>';
		$output .= '<td><textarea name="updateme" cols="60" rows="4"></textarea></td>';
		$output .= '</tr>';
		$output .= '<tr>';
		$output .= '<td><input type="submit" value="Tweet" /></td>';
		$output .= '</tr></table></form>';
		$output .= '</div>';
		
		//Get latest tweets
		$myTweets = $twClient->get('statuses/user_timeline', array('screen_name' => $username, 'count' => 5));
		
		//Display the latest tweets
		$output .= '<div class="tweet_list"><strong>Latest Tweets : </strong>';
		$output .= '<ul>';
		foreach($myTweets  as $tweet){
			$output .= '<li>'.$tweet->text.' <br />-<i>'.$tweet->created_at.'</i></li>';
		}
		$output .= '</ul></div>';
	}elseif(isset($_REQUEST['oauth_token']) && $_SESSION['token'] == $_REQUEST['oauth_token']){
		//Call Twitter API
		$twClient = new TwitterOAuth($consumerKey, $consumerSecret, $_SESSION['token'] , $_SESSION['token_secret']);
		
		//Get OAuth token
		$access_token = $twClient->getAccessToken($_REQUEST['oauth_verifier']);
		//If returns success
		if($twClient->http_code == '200'){
			//Storing access token data into session
			$_SESSION['status'] = 'verified';
			$_SESSION['request_vars'] = $access_token;
			
			//Get user profile data from twitter
			$userInfo = $twClient->get('account/verify_credentials');

			//Initialize User class
			
			//Insert or update user data to the database
			$name = explode(" ",$userInfo->name);
			$fname = isset($name[0])?$name[0]:'';
			$lname = isset($name[1])?$name[1]:'';
			$profileLink = 'https://twitter.com/'.$userInfo->screen_name;
			$twUserData = array(
				'oauth_provider'=> 'twitter',
				'oauth_uid'     => $userInfo->id,
				'first_name'    => $fname,
				'last_name'     => $lname,
				'email'         => '',
				'gender'        => '',
				'locale'        => $userInfo->lang,
				'picture'       => $userInfo->profile_image_url,
				'link'          => $profileLink,
				'username'		=> $userInfo->screen_name
			);
			
			$userData = $user->checkUser($twUserData);
			
			//Storing user data into session
			$_SESSION['userData'] = $userData;
			
			//Remove oauth token and secret from session
			unset($_SESSION['token']);
			unset($_SESSION['token_secret']);
			
			//Redirect the user back to the same page
			header('Location: ./');
		}else{
			$output = '<h3 style="color:red">Some problem occurred, please try again.</h3>';
		}
	}else{
		//Fresh authentication
		$twClient = new TwitterOAuth($consumerKey, $consumerSecret);
		$request_token = $twClient->getRequestToken($redirectURL);
		
		//Received token info from twitter
		$_SESSION['token']		 = $request_token['oauth_token'];
		$_SESSION['token_secret']= $request_token['oauth_token_secret'];
		
		//If authentication returns success
		if($twClient->http_code == '200'){
			//Get twitter oauth url
			$authUrl = $twClient->getAuthorizeURL($request_token['oauth_token']);
			
			//Display twitter login button
			$output = '<a style="text-decoration:underline !important;" href="'.filter_var($authUrl, FILTER_SANITIZE_URL).'">Connect</a>'; 
		}else{
			$output = '<h3 style="color:red">Error connecting to twitter! try again later!</h3>';
		}
	}
     

?>

		<div class="dropMain">
			<div class="SelDropdown no-drag">
				<div class="selheading">Sharing Options...</div> 
				<div class="inputHeader">
					<textarea class="inptextarea" name="caption" id="caption" cols="5" placeholder="Write a Captions..."></textarea>
				</div>
				<div class="myUlData"> 
					<ul>
						<?php 
						if($_SESSION['fb_access_token']!= ''){  ?>
						
						<li><i class="pull-left myimg"><img src="/gfx/socialmedia/FacebookIcon.png" ></i> Facebook <bdi class="pull-right chkarea"><input type="checkbox" name="" id="facebookchk" class="chk"></bdi></li>
						
						<?php  }else{    ?>
						
						<li>
						<i class="pull-left myimg"><img src="/gfx/socialmedia/FaceBookFaded.png" ></i> Facebook
						<bdi class="pull-right chkarea">
						<a href="<?= htmlspecialchars($loginUrl)?>" style="text-decoration:underline !important;">Connect</a></bdi></li>
						
						<?php } 
						if($_SESSION['google_code'] != ''){  ?>
						
						<li><i class="pull-left myimg"><img src="/gfx/socialmedia/YoutubeIcon.png" ></i> Youtube <bdi class="pull-right chkarea"><input type="checkbox" id="youtubechk" name="" class="chk"></bdi></li>
						
						<?php   } else{
								$request_to = $google_url . '?' . http_build_query($google_params);
						?>
						
						<li><i class="pull-left myimg"><img src="/gfx/socialmedia/YouTubeFaded.png" ></i> Youtube <bdi class="pull-right chkarea">
						<a href="<?php echo $request_to;?>" style="text-decoration:underline !important;">Connect</a></bdi></li>
						
						<?php } if(($_SESSION['twitter_oauth_token'] != '')&&($_SESSION['twitter_oauth_verifier'] != '')){  ?>
						
						<li><i class="pull-left myimg"><img src="/gfx/socialmedia/TwitterLogo.png"></i> Twitter <bdi class="pull-right chkarea"><a href="https://twitter.com/share" class=""><input type="checkbox" id="https://twitter.com/home?status=<?= htmlspecialchars($linkVideo); ?>" name="" class="chk twitter-share-button"></a></bdi></li>
						
						<?php }else{    ?>
						
						<li><i class="pull-left myimg"><img src="/gfx/socialmedia/TwitterFaded.png"></i> Twitter <bdi class="pull-right chkarea">
						<?php echo $output;?></bdi></li>
						
						<?php }  if($_SESSION['vimeo']){  ?>
						
						 <li><i class="pull-left myimg"><img src="/gfx/socialmedia/VimeoIcon.png" ></i> Vimeo <bdi class="pull-right chkarea"><input type="checkbox" id="http://www.vimeo.com/" name="" class="chk"></bdi></li>
						
						<?php   }else{   ?>
						
						<li><i class="pull-left myimg"><img src="/gfx/socialmedia/VimeoFaded.png" ></i> Vimeo <bdi class="pull-right chkarea">
						<a href="" style="text-decoration:underline !important;">Connect</a></bdi></li>  
						
						<?php   }if($_SESSION['linkedin_oauth_token'] != ''){  ?>
						
						<li><i class="pull-left myimg"><img src="/gfx/socialmedia/linkedinIcon.png" ></i> Linkedin <bdi class="pull-right chkarea"><input type="checkbox" id="https://www.linkedin.com/shareArticle?mini=true&url=<?= htmlspecialchars($linkVideo); ?>&title=Test%20title&summary=Test%20Summary&source=" name="" class="chk"></bdi></li>
						
						<?php } else{  ?>
						
						<li><i class="pull-left myimg"><img src="/gfx/socialmedia/LinkedInFaded.png" ></i> Linkedin <bdi class="pull-right chkarea">
						<a href="<?php echo $siteBaseUri; ?>cloudcode/external/linkedin/?oauth_init=1" style="text-decoration:underline !important;">Connect</a></bdi></li>
						
						<?php } if($_SESSION['insta_code']){  ?>						 
						
						<li><i class="pull-left myimg"><img src="/gfx/socialmedia/InstagramIcon.png" ></i> Instagram <bdi class="pull-right chkarea"><input type="checkbox" id="instagramchk" name="" class="chk"></bdi></li>
						
						<?php } else{  ?>
						
						<li><i class="pull-left myimg"><img src="/gfx/socialmedia/InstagramFaded.png" ></i> Instagram <bdi class="pull-right chkarea">
						<a href="<?= 'https://api.instagram.com/oauth/authorize/?client_id=' . INSTAGRAM_CLIENT_ID . '&redirect_uri=' . urlencode(INSTAGRAM_REDIRECT_URI) . '&response_type=code&scope=basic' ?>" style="text-decoration:underline !important;">Connect</a></bdi></li>
						
						<?php } 
						
						if ($_SESSION['google_code'] != '') { ?>
						
						<li><i class="pull-left myimg"><img src="/gfx/socialmedia/GooglePlusIcon.png" ></i> Google Plus <bdi class="pull-right chkarea"><input type="checkbox" id="https://plus.google.com/share?url=<?= htmlspecialchars($linkVideo); ?>" name="" class="chk"></bdi></li>
						
						<?php  }else{
								$request_to = $google_url . '?' . http_build_query($google_params);
						?>
						
						<li><i class="pull-left myimg"><img src="/gfx/socialmedia/GooglePlusFaded.png" ></i> Google Plus <bdi class="pull-right chkarea">
						<a href="<?=$request_to?>" style="text-decoration:underline !important;">Connect</a></bdi></li>
						
						<?php  }   ?>
						
					</ul>

					<div class="myUlData second-d">
						<li><i class="pull-left myimg">
							<img src="/gfx/socialmedia/EmailIcon.png" ></i> Email 
							<bdi class="pull-right dwon"></bdi>
						</li>

						<div class="ndropdown">
							<input type="text" name="email" class="emailData">
							<textarea class="textData inptextarea"></textarea>	

							<ul  class="mgo">
								<li><i class="pull-left"></i> Require authentication <bdi class="pull-right chkarea"><input type="checkbox" name="" class="chk"></bdi></li>
								<li><i class="pull-left"></i> Allow resharing <bdi class="pull-right chkarea"><input type="checkbox" name="" class="chk"></bdi></li>
							</ul>

						</div>
					</div>
					
					<script>
					function copyToClipboard(element) {						
 						var $temp = $("<input>");
						$("body").append($temp);
						$temp.val($(element).text()).select();
						document.execCommand("copy");
						$temp.remove();
						element.preventDefault();
					}
					</script>


					<ul>
						<li><i class="pull-left"></i> Make Embeddable <bdi class="pull-right chkarea">
						<input type="checkbox" name="" class="chk_ebbeded"></bdi></li>
						<div class="embededdata">
							<div class="full-w">
								<p>Embedding link for external sites:</p>
								<textarea class="textData inptextarea" id="embedp1<?=$vars['object']->id?>"><?= htmlspecialchars($embedCode); ?></textarea>
								<button onclick="copyToClipboard('#embedp1<?=$vars['object']->id?>')" type="button" style="    margin-left: 160px;margin-top: -10px;">Copy Link</button>
							</div>
							<div class="full-w">
								<p>Secret link for sharing:</p>
								<textarea class="textData inptextarea"><?= htmlspecialchars($linkVideo); ?></textarea>
							</div>
						</div>
					</ul>

					<div class="dropfooter center-block text-center">
					
					<!--	<button type="button" name="shareBtn" id="shareBtn" class="btn btn-sm btn-primary">Facebook Share</button> -->
						
					 <button type="button" name="share_social" class="share_social" class="btn btn-sm btn-primary">Share</button>  
					</div>
					 
				</div>
			</div>
		</div>
		
	<script>		
	(function(d, s, id) {
	  var js, fjs = d.getElementsByTagName(s)[0];
	  if (d.getElementById(id)) return;
	  js = d.createElement(s); js.id = id;
	  js.src = 'https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.11&appId=222491044953063';
	  fjs.parentNode.insertBefore(js, fjs);
	}(document, 'script', 'facebook-jssdk'));
	</script>	
 

<script>
  // This is called with the results from from FB.getLoginStatus().
  function statusChangeCallback(response) {
    console.log('statusChangeCallback');
    console.log(response);
    // The response object is returned with a status field that lets the
    // app know the current login status of the person.
    // Full docs on the response object can be found in the documentation
    // for FB.getLoginStatus().
    if (response.status === 'connected') {
      // Logged into your app and Facebook.
      testAPI();
    } else if (response.status === 'not_authorized') {
      // The person is logged into Facebook, but not your app.
      document.getElementById('status').innerHTML = 'Please log ' +
        'into this app.';
    } else {
      // The person is not logged into Facebook, so we're not sure if
      // they are logged into this app or not.
      document.getElementById('status').innerHTML = 'Please log ' +
        'into Facebook.';
    }
  }

  // This function is called when someone finishes with the Login
  // Button.  See the onlogin handler attached to it in the sample
  // code below.
  function checkLoginState() {
    FB.getLoginStatus(function(response) {
      statusChangeCallback(response);
    });
  }

  window.fbAsyncInit = function() {
  FB.init({
    appId      : '248990911853204',
    cookie     : true,  // enable cookies to allow the server to access 
                        // the session
    xfbml      : true,  // parse social plugins on this page
    version    : 'v2.10' // use version 2.2
  });
  FB.AppEvents.logPageView(); 

   
  FB.getLoginStatus(function(response) {
    statusChangeCallback(response);
  });

  };

  // Load the SDK asynchronously
  (function(d, s, id) {
    var js, fjs = d.getElementsByTagName(s)[0];
    if (d.getElementById(id)) return;
    js = d.createElement(s); js.id = id;
    js.src = "//connect.facebook.net/en_US/sdk.js";
    fjs.parentNode.insertBefore(js, fjs);
  }(document, 'script', 'facebook-jssdk'));

  // Here we run a very simple test of the Graph API after login is
  // successful.  See statusChangeCallback() for when this call is made.
  function testAPI() {
    console.log('Welcome!  Fetching your information.... ');
    FB.api('/me', function(response) {
      console.log('Successful login for: ' + response.name);
      document.getElementById('status').innerHTML =
        'Thanks for logging in, ' + response.name + '!';
    });
  }
  
    var datas = [];

		$(document).on('change','.chk',function(){
 			var attr = $(this).attr('id')
			var isdata = datas.find(function(res){
				return res == attr
			});

			if(isdata == undefined){
				datas.push(attr)
			}else {
				var indexOf = datas.indexOf(isdata)
				datas.splice(indexOf,1)
			}			
		});
		
		$('.share_social').click(function(){
			
			function fun_res(win){
				window.open(win);
			}

			var chk_0 = "facebookchk"
			var chk_1 = "youtubechk"
			var chk_2 = "https://twitter.com/home?status=<?= htmlspecialchars($linkVideo); ?>"
			var chk_3 = "http://www.vimeo.com/"
			var chk_4 = "https://www.linkedin.com/shareArticle?mini=true&url=<?= htmlspecialchars($linkVideo); ?>&title=Test%20title&summary=Test%20Summary&source="
			var chk_5 = "instagramchk"
			var chk_6 = "https://plus.google.com/share?url=<?= htmlspecialchars($linkVideo); ?>"

			var linkDatas = [chk_0,chk_1,chk_2,chk_3,chk_4,chk_5,chk_6] 			

			datas.map(function(res){
				console.log(res)
				for(var i = 0; i < linkDatas.length; i++){	
					console.log(linkDatas[i])			 
					if(res == linkDatas[i]){
						if(res == chk_0){
								var video_id = '<?php echo $vars['object']->id;?>';
								var download_video = '<?php echo $downloadvideo;?>';
								var caption = document.getElementById("caption").value;
								var facebook_access_token = '<?php echo $_SESSION['fb_access_token'];?>';

								//alert([video_id,download_video,caption,facebook_access_token]);
								
								$.ajax(
									{ 
										url: 'https://<?php echo $siteDomain; ?>:3333/s3Upload',
										data: {"facebook_access_token": facebook_access_token, "download_video": download_video, "caption": caption},
										type: 'post',
										beforeSend: function() {
											$('.dropfooter').html("Processing...");
										},
										success: function(result) {
											$(".append-data").removeClass('dropMain_ad');
											alert('Video has been shared!');
										},
										error: function(jqXHR, textStatus, err){
										   alert('text status '+textStatus+', err '+err);
									   }
									}
								);
						}else if(res == chk_1){
							var video_id = '<?php echo $vars['object']->id;?>';
							var download_video = '<?php echo $downloadvideo;?>';
							var caption = document.getElementById("caption").value;
							var facebook_access_token = '<?php echo $_SESSION['fb_access_token'];?>';
							$.ajax({ 
							url: '/ajax/manage/socialSettings.php',
							data: {"facebook_access_token": facebook_access_token, "download_video": download_video, "caption": caption},
							type: 'post',
							beforeSend: function() {
							$('.dropfooter').html("Processing...");
							},
							success: function(result){
							$(".append-data").removeClass('dropMain_ad');
							alert('Video has been shared!');

							}
							});
						}else if(res == chk_5){
								var video_id = '<?php echo $vars['object']->id;?>';
								var download_video = '<?php echo $downloadvideo;?>';					
								//alert(download_video);
								var facebook_access_token = '<?php echo $_SESSION['fb_access_token'];?>';
								$.ajax({ 
								url: '/ajax/manage/socialSettings.php',
								data: {"facebook_access_token": facebook_access_token, "download_video": download_video},
								type: 'post',
								beforeSend: function() {
								$('.dropfooter').html("Processing...");
								},
								success: function(result){
								$(".append-data").removeClass('dropMain_ad');
								alert('Video has been shared!');

								}
								});
						}else{
							fun_res(linkDatas[i])
						}		

						
					}
				}
			});
		});	  
      
</script>

 
<meta name="twitter:site" content="Easy Steps 2 Build Website">
<meta name="twitter:creator" content="@Steps2BuildSite">
<meta name="twitter:url" content="<?= htmlspecialchars($linkVideo); ?>">
<meta name="twitter:title" content="How to Add Twitter button to website">
<meta name="twitter:description" content="A complete guide on how to add twitter tweet button and twitter cards to website...">
 
 
  
 <script type="text/javascript">// <![CDATA[
!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];
if(!d.getElementById(id)){js=d.createElement(s);js.id=id;
js.async=true;
js.src="//platform.twitter.com/widgets.js";
fjs.parentNode.insertBefore(js,fjs);
}}(document,"script","twitter-wjs");
// ]]></script>
 
 