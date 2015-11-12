<?php
// Start the session
session_start();

require __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('Asia/Singapore');

// $log = new Monolog\Logger('name');
// $log->pushHandler(new Monolog\Handler\StreamHandler('app.log', Monolog\Logger::WARNING));
// $log->addWarning('Foo');

$app = new \Slim\Slim(array(
    'view' => new \Slim\Views\Twig()
));

$view = $app->view();
$view->parserOptions = array(
    'debug' => true,
);

$view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
);



/* profile page */
$app->get('/', function() use($app){
	$user_id = $_SESSION["user_id"];
	try
	{
		$db_twitter = new PDO('mysql:host=127.0.0.1;dbname=twitter', 'root', '');
		$db_twitter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sql = "SELECT * FROM user_posts WHERE user_id = '$user_id'";
		$sth = $db_twitter->prepare($sql);
		$sth->execute();

		if($sth->columnCount() == 0)
		{
			// 显示一个空白的profile page。
			echo "The web page is under construction.";
		}
		else
		{
			// 显示该用户发过的所有的twitter, pagination
			$results = $sth->fetchAll(PDO::FETCH_ASSOC);
			$app->render('timeline.html.twig', $results); 
		}
	}
	catch(PDOException $e)
	{
		echo $e->getMessage();
		die();
	}

	$db_twitter = null; 
});



/* send a post page*/
$app->post('/sendTwitter', function() use($app){
	$user_id = $_SESSION["user_id"];
	$msg = $app->request->post('msg');
	$post_date = date("Y-m-d G:i:s");
	try
	{
		$db_twitter = new PDO('mysql:host=127.0.0.1;dbname=twitter', 'root', '');
		$db_twitter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$sql = $db_twitter->prepare("INSERT INTO user_posts (user_id, msg, post_date) VALUES (?, ?, ?)");
		$sql->bindParam(1, $user_id, PDO::PARAM_STR);
		$sql->bindParam(2, $msg, PDO::PARAM_STR);
		$sql->bindParam(3, $post_date, PDO::PARAM_STR);
		$sql->execute();
	}
	catch(PDOException $e)
	{
		echo $e->getMessage();
		die();
	}
	
	$db_twitter = null; 
	$app->render('timeline.html.twig');
});


/* signUp Page*/
$app->get('/signUp', function() use($app){
	$status = $app->request->get('status');
	$app->render('signUp.html.twig', array('status' => $status));
})->name('signUp');

$app->post('/signUp', function () use($app){
    $email = trim($app->request->post('email'));
    $password = trim($app->request->post('password'));
    $RePassword = trim($app->request->post('RePassword'));
    $name = trim($app->request->post('name'));

    if(empty($email) || empty($password) || empty($RePassword) || empty($name))
    {
    	$error_message = "You must specify a value for email address, password and name.";
    }

    if(!isset($error_message))
	{
		$cleanEmail = filter_var($email, FILTER_SANITIZE_EMAIL);
		$cleanPassword = filter_var($password, FILTER_SANITIZE_STRING);
		$cleanRePassword = filter_var($RePassword, FILTER_SANITIZE_STRING);
		$cleanName = filter_var($name, FILTER_SANITIZE_STRING);

		if($cleanPassword != $cleanRePassword)
		{
			$error_message = 'You must confirm that the passwords are the same.';			
		}
		else
		{
			// #1 store register information into the 'twitter' database
			$cleanPasswordHash = password_hash($cleanPassword , PASSWORD_BCRYPT);
			try
			{
				$db_twitter = new PDO('mysql:host=127.0.0.1;dbname=twitter', 'root', '');
				$db_twitter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$sql = $db_twitter->prepare("INSERT INTO register_Info (email, password, name) VALUES (?, ?, ?)");
				$sql->bindParam(1, $cleanEmail, PDO::PARAM_STR);
				$sql->bindParam(2, $cleanPasswordHash, PDO::PARAM_STR);
				$sql->bindParam(3, $cleanName, PDO::PARAM_STR);

				$sql->execute();
			}
			catch(PDOException $e)
			{
				echo $e->getMessage();
				die();
			}
			
			$db_twitter = null;  // end of storing information

			#2 send messge to xiejun04512@gmail.com through swift mailer
			$transport = Swift_SmtpTransport::newInstance('smtp.postmarkapp.com', 2525, 'tls');
  			$transport->setUsername('51928d85-6ee8-4a70-9830-a9bcc17cbe9b');
  			$transport->setPassword('51928d85-6ee8-4a70-9830-a9bcc17cbe9b');
  		
  			$mailer = Swift_Mailer::newInstance($transport);

			$message = Swift_Message::newInstance();
			$message->setFrom(array('jun@xiejun.be' => 'DaTouLiTwitter'));
			$message->setSubject('register from twitter');
			$message->setTo(array('xiejun04512@gmail.com'));
			$data = "email: " . $cleanEmail . '<br />' . "Name: " . $cleanName;
			$message->setBody($data, 'text/html');

			$result = $mailer->send($message);

			if($result == true)
			{
				$app->redirect('/signUp?status=thanks'); //redirect to contact-thanks.php
			}
			else
			{
				$status = "fail";
				$app->render('signUp.html.twig', array(
					'status' => $status,
					'error_message' => $error_message				
				));
			}
		}
	}		
}); 



/* logIn Page*/
$app->get('/logIn', function() use($app){
	$app->render('logIn.html.twig');
})->name('logIn');

$app->post('/logIn', function () use($app){
    $email = trim($app->request->post('email'));
    $password = trim($app->request->post('password'));

    if(empty($email) || empty($password))
    {
		$status = "fail_empty";
		$error_message = "You must specify a value for email address and password."; 
		$app->render('logIn.html.twig', array(
			'status' => $status,
			'error_message' => $error_message
		));  	
    }

    if(!isset($error_message))
	{
		$cleanEmail = filter_var($email, FILTER_SANITIZE_EMAIL);
		$cleanPassword = filter_var($password, FILTER_SANITIZE_STRING);

		$cleanPasswordHash = password_hash($cleanPassword , PASSWORD_BCRYPT);

		try
		{
			$db_twitter = new PDO('mysql:host=127.0.0.1;dbname=twitter', 'root', '');
			$db_twitter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$sql = "SELECT * FROM register_Info WHERE email = '$cleanEmail'";

			$sth = $db_twitter->prepare($sql);
			$sth->execute();
			//$sth = $db_twitter->query($sql);

			if($sth->rowCount() == 0)
			{
				// 回到这个登录页面，提示email输入错误， 不存在这个email
				$status = "fail_email";
				$error_message = "This email is not correct.";
				$app->render('logIn.html.twig', array(
					'status' => $status,
					'error_message' => $error_message
				));
			}
			else
			{
				$result = $sth->fetchAll();
				$hash = $result[0]['password'];
				$id = $result[0]['id'];
				if(password_verify($cleanPassword, $hash))
				{
					// Set session variables
					$_SESSION["user_id"] = $id;

					$app->redirect('/'); // need to login to his own timeline
				}
				else
				{
					// 跳转回这个登录页面，提示密码错误
					$status = "fail_password";
					$error_message = "The password you provided is not correct.";
					$app->render('logIn.html.twig', array(
						'status' => $status,
						'error_message' => $error_message
					));
				}
			}		
		}
		catch(PDOException $e)
		{
			echo $e->getMessage();
			die();
		}

		$db_twitter = null;
	}
}); 

$app->run();