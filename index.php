<?php
require 'vendor/autoload.php';
date_default_timezone_set('America/Mexico_City');
session_start();

// configs
$config['displayErrorDetails'] = true;  //for dev
$config['addContentLengthHeader'] = false;

$config['db']['host']   = "localhost";
$config['db']['user']   = "carssa_db_usr";
$config['db']['pass']   = "SRERZYR37VTacmqv";
$config['db']['dbname'] = "carssa";

$app = new \Slim\App(['settings' => $config]);
$container = $app->getContainer();

// Logger for debug
$container['logger'] = function() {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler("log/app.log");
    $logger->pushHandler($file_handler);
    return $logger;
};

// Flash
$container['flash'] = function () {
    return new \Slim\Flash\Messages();
};

// Twig View helper
$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig('templates', [
        'cache' => false  //'cache' //disabled for dev
    ]);
    $view->addExtension(new \Slim\Views\TwigExtension(
        $container['router'],
        $container['request']->getUri()
    ));

    return $view;
};

// db in PDO
$container['db'] = function ($container) {
    $db = $container['settings']['db'];
    try{
        $pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
            $db['user'], $db['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("SET NAMES 'utf8'");
    }catch(Exception $e){
        echo 'No se pudo conectar a la base de datos.';
        exit();
    }
    return $pdo;
};

/*================================================ Routing GET ===================================================*/
$app->get('/', function($request, $response){
    $usuario = checar_usuario($this);
    if(empty($usuario)){
        return $response->withRedirect($this->router->pathFor('login'));
    }
    return $this->view->render($response, 'home.twig', [
        'flash' => $this->flash->getMessages(),
        'usuario' => $usuario
    ]);
})->setName('home');

$app->get('/clientes/[{id}]', function ($request, $response, $args) {
    $usuario = checar_usuario($this);
    if(empty($usuario)){
        return $response->withRedirect($this->router->pathFor('login'));
    }
    if($usuario['funcion'] != 'admin'){
         $this->flash->addMessage('error', 'No tiene el nivel de usuario requerido.');
        return $response->withRedirect($this->router->pathFor('home'));
    }
    if(empty($args['id'])){
        $clientes = clientes_lista($this);
        return $this->view->render($response, 'clientes.twig', [
            'flash' => $this->flash->getMessages(),
            'clientes' => $clientes,
            'usuario' => $usuario
        ]);
    }
    $id = filter_var($args['id'], FILTER_SANITIZE_NUMBER_INT);
    $cliente = cliente_detalle($this, $id);
    if(empty($cliente)){
        $this->flash->addMessage('error', 'No se encontro el cliente.');
        return $response->withRedirect($this->router->pathFor('clientes'));
    }
    return $this->view->render($response, 'cliente.twig', [
        'flash' => $this->flash->getMessages(),
        'cliente' => $cliente,
        'usuario' => $usuario
    ]);
})->setName('clientes');

$app->get('/nuevo/[{tipo}]', function ($request, $response, $args) {
    $usuario = checar_usuario($this);
    if(empty($usuario)){
        return $response->withRedirect($this->router->pathFor('login'));
    }
    if($usuario['funcion'] != 'admin'){
         $this->flash->addMessage('error', 'No tiene el nivel de usuario requerido.');
        return $response->withRedirect($this->router->pathFor('home'));
    }
    $tipo = filter_var($args['tipo'], FILTER_SANITIZE_STRING);
    switch($tipo){
        case 'cliente':
            return $this->view->render($response, 'cliente.twig', [
                'flash' => $this->flash->getMessages(),
                'usuario' => $usuario
            ]);
    }
    // $this->flash->addMessage('error', 'No se especifico un tipo.');
    return $response->withRedirect($this->router->pathFor('home'));
})->setName('nuevo');

$app->get('/login', function($request, $response){
    $demo = demo_keys($this);
    return $this->view->render($response, 'login.twig', [
        'flash' => $this->flash->getMessages(),
        'demo' => $demo
    ]);
})->setName('login');

$app->get('/logout', function($request, $response){
    if(session_destroy()) {
        session_start();
    }
    return $this->view->render($response, 'logout.twig', [
        'flash' => $this->flash->getMessages()
    ]);
})->setName('logout');

$app->get('/contacto', function($request, $response){
    return $this->view->render($response, 'contacto.twig', [
        'flash' => $this->flash->getMessages()
    ]);
})->setName('contacto');

/*================================================ Routing POST ===================================================*/
$app->post('/login', function($request, $response){
    $data = $request->getParsedBody();

    if(!empty($data['usuario']) && !empty($data['clave'])){
        $login_data = [];
        $login_data['usuario'] = filter_var($data['usuario'], FILTER_SANITIZE_STRING);
        $login_data['clave'] = filter_var($data['clave'], FILTER_SANITIZE_STRING);
        
        
        $usuario = checar_clave($this, $login_data['usuario'], $login_data['clave']);
        if(empty($usuario)){
            $this->flash->addMessage('error', 'Nombre de usuario o clave incorrecta.');
            return $response->withRedirect($this->router->pathFor('login'));
        }
        $_SESSION['login_user'] = $usuario['usuario'];
        return $response->withRedirect($this->router->pathFor('home'));
    }else{
        $this->flash->addMessage('error', 'Tienes que llenar los campos requeridos.');
        return $response->withRedirect($this->router->pathFor('login'));
    }
});

$app->post('/contacto', function($request, $response){
    $data = $request->getParsedBody();

    if(!empty($data['nombre']) && !empty($data['email']) && !empty($data['mensaje'])){
        $contact_data = [];
        $contact_data['nombre'] = filter_var($data['nombre'], FILTER_SANITIZE_STRING);
        $contact_data['email'] = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $contact_data['mensaje'] = filter_var($data['mensaje'], FILTER_SANITIZE_STRING);
        
        $transport = Swift_MailTransport::newInstance();
        $mailer = Swift_Mailer::newInstance($transport);
        $message = Swift_Message::newInstance();
        $message->setSubject('Asteroide 2DI Contacto');
        $message->setFrom(array(
            $contact_data['email'] => $contact_data['nombre']
        ));
        $message->setTo(array(
            'orate911@hotmail.com' => 'Yo'
        ));
        $message->setBody($contact_data['mensaje']);
        $result = $mailer->send($message);  //rand(0,1);
        
        if($result > 0){
            $this->flash->addMessage('exito', 'Su mensaje ha sido recibido. Muy pronto nos contactaremos con usted.');
            return $response->withRedirect($this->router->pathFor('home'));
        }else{
            $this->logger->addInfo('Fallo envio de email');
            $this->flash->addMessage('error', 'Hubo un problema al enviar su email. Inténtelo de nuevo.');
            return $response->withRedirect($this->router->pathFor('contacto'));
        }
        
    }else{
        $this->flash->addMessage('error', 'Tienes que llenar los campos requeridos.');
        return $response->withRedirect($this->router->pathFor('contacto'));
    }
});

$app->post('/nuevo/cliente', function($request, $response){
    $data = $request->getParsedBody();

    if(!empty($data['usuario']) && !empty($data['clave']) && !empty($data['email']) && !empty($data['nombre'])){
        $nuevo_data = [];
        $nuevo_data['usuario'] = filter_var($data['usuario'], FILTER_SANITIZE_STRING);
        if(checar_nuevo_usuario($this, $nuevo_data['usuario'])){
            $this->flash->addMessage('error', 'El nombre de usuario ya esta registrado. Escriba uno diferente.');
            return $response->withRedirect($this->router->pathFor('nuevo').'cliente');
        }
        $nuevo_data['email'] = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $nuevo_data['clave'] = filter_var($data['clave'], FILTER_SANITIZE_SPECIAL_CHARS);
        $nuevo_data['nombre'] = filter_var($data['nombre'], FILTER_SANITIZE_STRING);
        
        $usuario = nuevo_cliente($this, $nuevo_data);
        if($usuario){
            $this->flash->addMessage('exito', 'El cliente ha sido creado.');
            return $response->withRedirect($this->router->pathFor('clientes'));
        }else{
            $this->logger->addInfo('Fallo creacion de nuevo cliente.');
            $this->flash->addMessage('error', 'Hubo un problema al crear el cliente. Contacte con un administrador.');
            return $response->withRedirect($this->router->pathFor('clientes'));
        }
        
    }else{
        $this->flash->addMessage('error', 'Tienes que llenar los campos requeridos.');
        return $response->withRedirect($this->router->pathFor('nuevo').'cliente');
    }
});

$app->post('/clientes/[{id}]', function ($request, $response, $args) {
    $data = $request->getParsedBody();

    if(!empty($data['usuario']) && !empty($data['clave']) && !empty($data['email']) && !empty($data['nombre'])){
        $nuevo_data = [];
        $nuevo_data['usuario'] = filter_var($data['usuario'], FILTER_SANITIZE_STRING);
        if(checar_nuevo_usuario($this, $nuevo_data['usuario'])){
            $this->flash->addMessage('error', 'El nombre de usuario ya esta registrado. Escriba uno diferente.');
            return $response->withRedirect($this->router->pathFor('nuevo').'cliente');
        }
        $nuevo_data['email'] = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $nuevo_data['clave'] = filter_var($data['clave'], FILTER_SANITIZE_SPECIAL_CHARS);
        $nuevo_data['nombre'] = filter_var($data['nombre'], FILTER_SANITIZE_STRING);
        
        $usuario = nuevo_cliente($this, $nuevo_data);
        if($usuario){
            $this->flash->addMessage('exito', 'El cliente ha sido creado.');
            return $response->withRedirect($this->router->pathFor('clientes'));
        }else{
            $this->logger->addInfo('Fallo creacion de nuevo cliente.');
            $this->flash->addMessage('error', 'Hubo un problema al crear el cliente. Contacte con un administrador.');
            return $response->withRedirect($this->router->pathFor('clientes'));
        }
        
    }else{
        $this->flash->addMessage('error', 'Tienes que llenar los campos requeridos.');
        return $response->withRedirect($this->router->pathFor('nuevo').'cliente');
    }
});

$app->run();

/*================================================ DB Model ==================================================*/
function demo_keys($c){
    try{
        $results = $c['db']->query("
            SELECT id, funcion, descripcion
            FROM funciones
        ");
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    $demo = $results->fetchAll();
    
    foreach($demo as &$func){
       try{
            $results = $c['db']->prepare("
                SELECT usuario, clave
                FROM usuarios
                WHERE funcion_id = ?
            ");
            $results->bindParam(1, $func['id']);
            $results->execute();
        }catch(Exception $e){
            echo ('No se pudo leer la informacion de la base de datos');
            exit();
        }
        $usr = $results->fetch();
        if(empty($usr)){
            $func['usuario'] = 'no existe';
            $func['clave'] = 'no existe';
        }else{
            $func['usuario'] = $usr['usuario'];
            $func['clave'] = $usr['clave'];
        }
    }
    unset($func);
    return $demo;
}

function checar_nuevo_usuario($c, $nombre){
    try{
        $results = $c['db']->prepare("
            SELECT usuario
            FROM usuarios
            WHERE usuario = ?
        ");
        $results->bindParam(1, $nombre);
        $results->execute();
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    $usuario = $results->fetch();
    return $usuario;
}

function checar_usuario($c){
    $usuario = $_SESSION['login_user'];
    if(empty($usuario)) return false;
    try{
        $results = $c['db']->prepare("
            SELECT usuarios.usuario, funciones.funcion
            FROM usuarios
            JOIN funciones on usuarios.funcion_id = funciones.id
            WHERE usuarios.usuario = ?
        ");
        $results->bindParam(1, $usuario);
        $results->execute();
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    $usuario = $results->fetch();
    //echo var_dump($usuario); exit();
    return $usuario;
}

function checar_clave($c, $usr, $clv){
    try{
        $results = $c['db']->prepare("
            SELECT usuario
            FROM usuarios
            WHERE usuario = ?
            AND clave = ?
        ");
        $results->bindParam(1, $usr);
        $results->bindParam(2, $clv);
        $results->execute();
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    $usuario = $results->fetch();
    return $usuario;
}

function clientes_lista($c){
    try{
        //echo var_dump($c);
        $results = $c['db']->query("
            SELECT clientes.nombre, clientes.id, usuarios.usuario, usuarios.email
            FROM clientes
            LEFT OUTER JOIN usuarios
            ON clientes.usuario_id = usuarios.id
        ");
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    $clientes = $results->fetchAll();
    return $clientes;
}

function cliente_detalle($c, $id){
    try{
        $results = $c['db']->prepare("
            SELECT clientes.nombre, clientes.id, usuarios.usuario, usuarios.email
            FROM clientes
            LEFT OUTER JOIN usuarios
            ON clientes.usuario_id = usuarios.id
            WHERE clientes.id = ?
        ");
        $results->bindValue(1, $id, PDO::PARAM_INT);
        $results->execute();
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    $cliente = $results->fetch();
    return $cliente;
}

function nuevo_cliente($c, $data){
    try{
        $results = $c['db']->prepare("
            INSERT INTO usuarios
            (usuario, clave, email, funcion_id)
            VALUES
            (?, ?, ?, 3)
        ");
        $results->bindParam(1, $data['usuario']);
        $results->bindParam(2, $data['clave']);
        $results->bindParam(3, $data['email']);
        $results->execute();
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    $id = $c['db']->lastInsertId();
    try{
        $results = $c['db']->prepare("
            INSERT INTO clientes
            (nombre, usuario_id)
            VALUES
            (?, ?)
        ");
        $results->bindParam(1, $data['nombre']);
        $results->bindValue(2, $id, PDO::PARAM_INT);
        $results->execute();
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    return true;
}