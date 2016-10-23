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
        $demo = demo_keys($this);
        return $this->view->render($response, 'home.twig', [
            'flash' => $this->flash->getMessages(),
            'demo' => $demo
        ]);
    }
    return $this->view->render($response, 'home.twig', [
        'flash' => $this->flash->getMessages(),
        'usuario' => $usuario
    ]);
})->setName('home');

$app->get('/clientes', function ($request, $response) {
    $usuario = checar_usuario($this);
    if(empty($usuario)){
        return $response->withRedirect($this->router->pathFor('home'));
    }
    if($usuario['funcion'] != 'admin'){
        $this->flash->addMessage('error', 'No tiene el nivel de usuario requerido.');
        return $response->withRedirect($this->router->pathFor('home'));
    }
    $clientes = clientes_lista($this);
    return $this->view->render($response, 'clientes.twig', [
        'flash' => $this->flash->getMessages(),
        'clientes' => $clientes,
        'usuario' => $usuario
    ]);
})->setName('clientes-lista');

$app->get('/clientes/{id}', function ($request, $response, $args) {
    $usuario = checar_usuario($this);
    if(empty($usuario)){
        return $response->withRedirect($this->router->pathFor('home'));
    }
    $id = filter_var($args['id'], FILTER_SANITIZE_NUMBER_INT);
    if($usuario['funcion'] != 'admin' && $usuario['id'] != $id){
         $this->flash->addMessage('error', 'No tiene el nivel de usuario requerido.');
        return $response->withRedirect($this->router->pathFor('home'));
    }
    $cliente = cliente_detalle($this, $id);
    if(empty($cliente)){
        $this->flash->addMessage('error', 'No se encontro el cliente.');
        return $response->withRedirect($this->router->pathFor('home'));
    }
    return $this->view->render($response, 'cliente-detalle.twig', [
        'flash' => $this->flash->getMessages(),
        'cliente' => $cliente,
        'usuario' => $usuario
    ]);
})->setName('clientes');

$app->get('/nuevo/cliente', function ($request, $response) {
    $usuario = checar_usuario($this);
    if(empty($usuario)){
        return $this->view->render($response, 'cliente-nuevo.twig', [
            'flash' => $this->flash->getMessages()
        ]);
    }
    if($usuario['funcion'] == 'admin'){
         return $this->view->render($response, 'cliente-nuevo.twig', [
            'flash' => $this->flash->getMessages(),
            'usuario' => $usuario
        ]);
    }
    return $response->withRedirect($this->router->pathFor('home'));
})->setName('nuevo-cliente');

/*$app->get('/nuevo/{tipo}', function ($request, $response, $args) {
    $usuario = checar_usuario($this);
    if(empty($usuario)){
        return $response->withRedirect($this->router->pathFor('home'));
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
})->setName('nuevo');*/

$app->get('/logout', function($request, $response){
    if(session_destroy()) {
        session_start();
    }
    $this->flash->addMessage('exito', '¡Hasta pronto!');
    return $response->withRedirect($this->router->pathFor('home'));
})->setName('logout');

$app->get('/contacto', function($request, $response){
    return $this->view->render($response, 'contacto.twig', [
        'flash' => $this->flash->getMessages()
    ]);
})->setName('contacto');

/*================================================ Routing POST ===================================================*/
$app->post('/login', function($request, $response){
    $data = $request->getParsedBody();
    // validacion
    $errores = false;
    if(empty($data['usuario'])){
        $this->flash->addMessage('usrName', 'Escriba su nombre de usuario');
        $errores = true;
    }
    if(empty($data['clave'])){
        $this->flash->addMessage('clave', 'Escriba su clave de usuario');
        $errores = true;
    }
    if($errores){
        $this->flash->addMessage('error', 'Tienes que llenar los campos requeridos.');
        return $response->withRedirect($this->router->pathFor('home'));
    }
    // limpieza
    $login_data = [];
    $login_data['usuario'] = filter_var($data['usuario'], FILTER_SANITIZE_STRING);
    $login_data['clave'] = filter_var($data['clave'], FILTER_SANITIZE_STRING);
    // checar_clave regresa el usuario si la clave es correcta
    /*¡¡¡¡¡¡¡¡¡¡¡¡¡ Agregar encriptacion a la clave !!!!!!!!!!!!*/
    $usuario = checar_clave($this, $login_data['usuario'], $login_data['clave']);
    if(empty($usuario)){
        $this->flash->addMessage('error', 'Nombre de usuario o clave incorrecta.');
        return $response->withRedirect($this->router->pathFor('home'));
    }
    // registrar seseion
    $_SESSION['login_user'] = $usuario['usuario'];
    $this->flash->addMessage('exito', '¡Bienvenido ' . $usuario['usuario'] . ' !');
    return $response->withRedirect($this->router->pathFor('home'));
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
    $errores = false;
    $data = $request->getParsedBody();
    // validacion
    if(empty($data['usuario'])){
        $this->flash->addMessage('usrName', 'Escriba su nombre de usuario');
        $errores = true;
    }
    if(empty($data['clave'])){
        $this->flash->addMessage('clave', 'Escriba su clave de usuario');
        $errores = true;
    }
    if(empty($data['email'])){
        $this->flash->addMessage('email', 'Escriba su email');
        $errores = true;
    }
    if(empty($data['nombre'])){
        $this->flash->addMessage('nombre', 'Escriba su nombre');
        $errores = true;
    }
    if($errores){
        $this->flash->addMessage('error', 'Tienes que llenar los campos requeridos.');
        return $response->withRedirect($this->router->pathFor('nuevo-cliente'));
    }
    // checar_nuevo_usuario verifica si usuario es unico
    $nuevo_data = [];
    $nuevo_data['usuario'] = filter_var($data['usuario'], FILTER_SANITIZE_STRING);
    if(checar_nuevo_usuario($this, $nuevo_data['usuario'])){
        $this->flash->addMessage('usrName', 'El nombre de usuario ya esta registrado. Escriba uno diferente.');
        $errores = true;
    }
    // checar_email
    $nuevo_data['email'] = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    if(!filter_var($nuevo_data['email'], FILTER_VALIDATE_EMAIL)){
        $this->flash->addMessage('email', 'El email no tiene un formato válido.');
        $errores = true;
    }
    if($errores){
        return $response->withRedirect($this->router->pathFor('nuevo-cliente'));
    }
    $nuevo_data['clave'] = filter_var($data['clave'], FILTER_SANITIZE_SPECIAL_CHARS);
    $nuevo_data['nombre'] = filter_var($data['nombre'], FILTER_SANITIZE_STRING);
    // cliente_nuevo crea el cliente
    $usuario = cliente_nuevo($this, $nuevo_data);
    if(empty($usuario)){
        $this->logger->addInfo('Fallo creacion de nuevo cliente.');
        $this->flash->addMessage('error', 'Hubo un problema al crear el cliente.');
        if(empty($data['id'])){
            return $response->withRedirect($this->router->pathFor('home'));
        }
        return $response->withRedirect($this->router->pathFor('clientes-lista'));
    }
    $this->flash->addMessage('exito', 'El cliente ha sido creado.');
    if($data['funcion'] != 'admin'){
        return $response->withRedirect($this->router->pathFor('home'));
    }
    return $response->withRedirect($this->router->pathFor('clientes-lista'));
});

$app->post('/clientes/{id}', function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $id = filter_var($args['id'], FILTER_SANITIZE_NUMBER_INT);
    
    $nuevo_data = [];
    foreach($data as $key => $value){
        switch($key){
            case 'nombre':
                if(!empty($value)) $nuevo_data['nombre'] = filter_var($data['nombre'], FILTER_SANITIZE_STRING);
                break;
            case 'clave':
                if(!empty($value)) $nuevo_data['clave'] = filter_var($data['clave'], FILTER_SANITIZE_SPECIAL_CHARS);
                break;
            case 'email':
                if(!empty($value)) $nuevo_data['email'] = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
                break;
            default:
        }
    }
    if(empty($nuevo_data)){
        $this->flash->addMessage('error', 'La forma para actualizar esta vacia.');
        return $response->withRedirect($this->router->pathFor('clientes', [id => $id]));
    }
    // cliente_update 
    $usuario = cliente_update($this, $nuevo_data, $id);
    if(empty($usuario)){
        $this->logger->addInfo('Fallo actualización de cliente.');
        $this->flash->addMessage('error', 'Hubo un problema al actualizar el cliente.');
        return $response->withRedirect($this->router->pathFor('clientes', [id => $id]));
    }
    $this->flash->addMessage('exito', 'El cliente ha sido actualizado.');
    return $response->withRedirect($this->router->pathFor('clientes', [id => $id]));
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
    if(empty($_SESSION['login_user'])) return false;
    $usuario = filter_var($_SESSION['login_user'], FILTER_SANITIZE_STRING);
    try{
        $results = $c['db']->prepare("
            SELECT usuarios.usuario, usuarios.id, funciones.funcion
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

function checar_email($c, $email){
    if(preg_match("/^[-!#$%&'*+/0-9=?A-Z^_a-z{|}~](\.?[-!#$%&'*+/0-9=?A-Z^_a-z{|}~])*
        @[a-zA-Z](-?[a-zA-Z0-9])*(\.[a-zA-Z](-?[a-zA-Z0-9])*)+$/", $email)){
            return true;
        }
    return false;
}

function clientes_lista($c){
    try{
        //echo var_dump($c); exit();
        $results = $c['db']->query("
            SELECT clientes.nombre, usuarios.id, usuarios.usuario, usuarios.email
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
            SELECT clientes.nombre, usuarios.id, usuarios.usuario, usuarios.email
            FROM clientes
            LEFT OUTER JOIN usuarios
            ON clientes.usuario_id = usuarios.id
            WHERE usuarios.id = ?
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

function cliente_nuevo($c, $data){
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

function cliente_update($c, $data, $id){
    if(array_key_exists('clave', $data)){
        try{
            $results = $c['db']->prepare("
                UPDATE usuarios SET
                clave = ?
                WHERE id = ?
            ");
            $results->bindParam(1, $data['clave']);
            $results->bindValue(2, $id, PDO::PARAM_INT);
            $results->execute();
        }catch(Exception $e){
            echo ('No se pudo leer la informacion de la base de datos');
            exit();
        }
    }
    if(array_key_exists('email', $data)){
        try{
            $results = $c['db']->prepare("
                UPDATE usuarios SET
                email = ?
                WHERE id = ?
            ");
            $results->bindParam(1, $data['email']);
            $results->bindValue(2, $id, PDO::PARAM_INT);
            $results->execute();
        }catch(Exception $e){
            echo ('No se pudo leer la informacion de la base de datos');
            exit();
        }
    }
    if(array_key_exists('nombre', $data)){
        try{
            $results = $c['db']->prepare("
                UPDATE clientes SET
                nombre = ?
                WHERE usuario_id = ?
            ");
            $results->bindParam(1, $data['nombre']);
            $results->bindValue(2, $id, PDO::PARAM_INT);
            $results->execute();
        }catch(Exception $e){
            echo ('No se pudo leer la informacion de la base de datos');
            exit();
        }
    }
    return true;
}