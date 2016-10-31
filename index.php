<?php
require 'vendor/autoload.php';
date_default_timezone_set('America/Mexico_City');
session_start();

// configs
require 'res/config.php';
require 'res/db.php';

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

// Flash
$container['pdf'] = function () {
    class PDF extends FPDF {
        function Header(){
            //$this->Image('logo_pb.png',10,8,33);
            $this->SetFont('Arial','',10);
            $this->Cell(80);
            $this->Cell(30,10, $this->titulo,0,0,'C');
            $this->Ln(20);
        }
        function Footer(){
            $this->SetY(-15);
            $this->SetFont('Arial','I',8);
            $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
        }
    }
    $pdf = new \PDF();
    //pdf->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);
    //$pdf->SetFont('DejaVu','',14);
    //$pdf->AddFont('Caladea','','caladea-regular.php');
    return $pdf;
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
    if($usuario['funcion'] == 'cliente'){
        return $response->withRedirect($this->router->pathFor('detalle-cliente', [id => $usuario['id']]));
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

$app->get('/detalle/cliente/{id}', function ($request, $response, $args) {
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
    //
    $direcciones = direcciones_lista($this, $id);
    $dirs = array_column($direcciones, 'direccion', 'id');
    $estados = estados_lista($this, $id);
    $edos = array_column($estados, 'estado', 'id');
    //var_dump($edos);
    $entregas = entregas_lista($this, $id);
    foreach($entregas as $index => &$entrega){
        $entrega['direccion_dest'] = $dirs[$entrega['destinatario_dir']];
        $entrega['direccion_remi'] = $dirs[$entrega['remitente_dir']];
        $entrega['estado'] = $edos[$entrega['estado_id']];
    }
    return $this->view->render($response, 'cliente-detalle.twig', [
        'flash' => $this->flash->getMessages(),
        'cliente' => $cliente,
        'usuario' => $usuario,
        'direcciones' => $direcciones,
        'entregas' => $entregas
    ]);
})->setName('detalle-cliente');

$app->get('/update/cliente/{id}', function ($request, $response, $args) {
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
    return $this->view->render($response, 'cliente-update.twig', [
        'flash' => $this->flash->getMessages(),
        'cliente' => $cliente,
        'usuario' => $usuario
    ]);
})->setName('update-cliente');

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

$app->get('/nuevo/direccion/{id}', function ($request, $response, $args) {
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
    return $this->view->render($response, 'direccion-nueva.twig', [
        'flash' => $this->flash->getMessages(),
        'usuario' => $usuario
    ]);
})->setName('nuevo-direccion');

$app->get('/nuevo/entrega/{id}', function ($request, $response, $args) {
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
    //
    $direcciones = direcciones_lista($this, $id);
    $tipos_entrega = tipos_entrega_lista($this, $id);
    $coberturas = coberturas_lista($this, $id);
    /*if(count($direcciones) < 2){
        $this->flash->addMessage('error', 'Tiene que definir al menos dos direcciones para este cliente.');
        return $response->withRedirect($this->router->pathFor('detalle-cliente', [id => $id]));
    }*/
    return $this->view->render($response, 'entrega-nueva.twig', [
        'flash' => $this->flash->getMessages(),
        'usuario' => $usuario,
        'direcciones' => $direcciones,
        'tipos_entrega' => $tipos_entrega,
        'coberturas' => $coberturas
    ]);
})->setName('nuevo-entrega');

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

$app->get('/email/entrega/{id}/{eid}', function($request, $response, $args){
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
    $eid = filter_var($args['eid'], FILTER_SANITIZE_NUMBER_INT);
    $entrega = entrega_detalle($this, $eid);
    if(empty($entrega)){
        $this->flash->addMessage('error', 'No se encontro el cliente.');
        return $response->withRedirect($this->router->pathFor('home'));
    }
    $remitente = direccion_detalle($this, $entrega['remitente_dir']);
    $destinatario = direccion_detalle($this, $entrega['destinatario_dir']);
    //cuerpo del mensaje
    $dir_keys = [
        'calle' => 'Direccion',
        'entre' => 'Entre',
        'colonia' => 'Colonia',
        'ciudad' => 'Ciudad',
        'estado' => 'Estado',
        'pais' => 'pais',
        'cp' => 'C.P.',
        'telefono' => 'Telefono'
    ];
    $cuerpo = '';
    $cuerpo .= '<p>Entrega No. '.$entrega['id'].' Cliente No. '.$cliente['usuario_id'].'</p>';
    $cuerpo .= '<h1>Remitente</h1>';
    $cuerpo .= '<p>Nombre: '.$cliente['nombre'].'</p>';
    $cuerpo .= '<p>Razon Social: '.$cliente['razon'].'</p>';
    foreach($dir_keys as $key => $label){
        $cuerpo .= '<p>'.$label.': '.$remitente[$key].'</p>';
    }
    $cuerpo .= '<h1>Destinatario</h1>';
    $cuerpo .= '<p>Nombre: '.$entrega['destinatario_nombre'].'</p>';
    $cuerpo .= '<p>Razon Social: '.$entrega['destinatario_razon'].'</p>';
    foreach($dir_keys as $key => $label){
        $cuerpo .= '<p>'.$label.': '.$destinatario[$key].'</p>';
    }
    $cuerpo .= '<h1>Tipo de Entrega</h1>';
    $cuerpo .= '<p>'.$entrega['tipo_entrega'].'</p>';
    $cuerpo .= '<h1>Tipo de Cobertura</h1>';
    $cuerpo .= '<p>'.$entrega['cobertura'].'</p>';
    //
    $transport = Swift_MailTransport::newInstance();
    $mailer = Swift_Mailer::newInstance($transport);
    $message = Swift_Message::newInstance();
    $message->setSubject('Asteroide 2DI Entrega');
    $message->setFrom(array(
        $cliente['email'] => $cliente['nombre']
    ));
    $message->setTo(array(
        'orate911@hotmail.com' => 'Yo'
    ));
    $message->setBody($cuerpo, 'text/html');
    if($this['settings']['enviarMail']){
        $result = $mailer->send($message);
    }else{
        $result = rand(0,1);
    }

    if($result > 0){
        $this->flash->addMessage('exito', 'Su mensaje ha sido recibido. Muy pronto nos contactaremos con usted.');
        return $response->withRedirect($this->router->pathFor('detalle-cliente', [id => $id]));
    }else{
        $this->logger->addInfo('Fallo envio de email');
        $this->flash->addMessage('error', 'Hubo un problema al enviar su email. Inténtelo de nuevo.');
        return $response->withRedirect($this->router->pathFor('detalle-cliente', [id => $id]));
    }
})->setName('email-entrega');

$app->get('/imprimir/entrega/{id}/{eid}', function($request, $response, $args){
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
    $eid = filter_var($args['eid'], FILTER_SANITIZE_NUMBER_INT);
    $entrega = entrega_detalle($this, $eid);
    if(empty($entrega)){
        $this->flash->addMessage('error', 'No se encontro el cliente.');
        return $response->withRedirect($this->router->pathFor('home'));
    }
    $remitente = direccion_detalle($this, $entrega['remitente_dir']);
    $destinatario = direccion_detalle($this, $entrega['destinatario_dir']);
    //
    $dir_keys = [
        'calle' => 'Direccion',
        'entre' => 'Entre',
        'colonia' => 'Colonia',
        'ciudad' => 'Ciudad',
        'estado' => 'Estado',
        'pais' => 'pais',
        'cp' => 'C.P.',
        'telefono' => 'Telefono'
    ];
    $response = $response->withHeader( 'Content-type', 'application/pdf');
    $this->pdf->titulo = 'Entrega No. '.$entrega['id'].' Cliente No. '.$cliente['usuario_id'];
    $this->pdf->AliasNbPages();
    $this->pdf->AddPage();
    $this->pdf->SetFont('Arial','',12);
    //imagen
    $this->pdf->Image('res/pdf_640.png',10,10,16);
    $this->pdf->setY(35);

    //remitente
    $this->pdf->Cell(120,7,utf8_encode('Remitente'),1,0);
    $this->pdf->Ln();
    
    $this->pdf->SetFont('','B');
    $this->pdf->Cell(40,5,utf8_encode('Nombre'));
    $this->pdf->SetFont('','');
    $this->pdf->Write(5,$cliente['nombre']);
    $this->pdf->Ln();
    
    $this->pdf->SetFont('','B');
    $this->pdf->Cell(40,5,utf8_encode('Razon Social'));
    $this->pdf->SetFont('','');
    $this->pdf->Write(5,$cliente['razon']);
    $this->pdf->Ln();

    foreach($dir_keys as $key => $label){
        $this->pdf->SetFont('','B');
        $this->pdf->Cell(40,5,utf8_encode($label));
        $this->pdf->SetFont('','');
        $this->pdf->Write(5,$remitente[$key]);
        $this->pdf->Ln();
    }
    $this->pdf->Ln(5);

    //destinatario
    $this->pdf->Cell(120,7,utf8_encode('Destinatario'),1,0);
    $this->pdf->Ln();
    
    $this->pdf->SetFont('','B');
    $this->pdf->Cell(40,5,utf8_encode('Nombre'));
    $this->pdf->SetFont('','');
    $this->pdf->Write(5,$entrega['destinatario_nombre']);
    $this->pdf->Ln();
    
    $this->pdf->SetFont('','B');
    $this->pdf->Cell(40,5,utf8_encode('Razon Social'));
    $this->pdf->SetFont('','');
    $this->pdf->Write(5,$entrega['destinatario_razon']);
    $this->pdf->Ln();

    foreach($dir_keys as $key => $label){
        $this->pdf->SetFont('','B');
        $this->pdf->Cell(40,5,utf8_encode($label));
        $this->pdf->SetFont('','');
        $this->pdf->Write(5,$destinatario[$key]);
        $this->pdf->Ln();
    }
    $this->pdf->Ln(5);

    //tipo_entrega
    $this->pdf->Cell(120,7,utf8_encode('Tipo de Entrega'),1,0);
    $this->pdf->Ln();
    $this->pdf->Write(5,$entrega['tipo_entrega']);
    $this->pdf->Ln(10);

    //tipo_entrega
    $this->pdf->Cell(120,7,utf8_encode('Tipo de Cobertura'),1,0);
    $this->pdf->Ln();
    $this->pdf->Write(5,$entrega['cobertura']);
    $this->pdf->Ln(10);

    $this->pdf->Output();
    return $response;//->withRedirect($this->router->pathFor('detalle-cliente', [id => $id]));
})->setName('imprimir-entrega');

/*================================================ Routing POST ===================================================*/
$app->post('/login', function($request, $response){
    $data = $request->getParsedBody();
    // validacion
    $errores = false;
    if(empty($data['usuario'])){
        $this->flash->addMessage('usrName', 'El campo es requerido');
        $errores = true;
    }
    if(empty($data['clave'])){
        $this->flash->addMessage('clave', 'El campo es requerido');
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

$app->post('/update/cliente/{id}', function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $id = filter_var($args['id'], FILTER_SANITIZE_NUMBER_INT);
    
    $nuevo_data = [];
    foreach($data as $key => $value){
        switch($key){
            case 'nombre':
                if(!empty($value)) $nuevo_data['nombre'] = filter_var($data['nombre'], FILTER_SANITIZE_STRING);
                break;
            case 'razon':
                if(!empty($value)) $nuevo_data['razon'] = filter_var($data['razon'], FILTER_SANITIZE_STRING);
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
        return $response->withRedirect($this->router->pathFor('update-cliente', [id => $id]));
    }
    // cliente_update 
    $usuario = cliente_update($this, $nuevo_data, $id);
    if(empty($usuario)){
        $this->logger->addInfo('Fallo actualización de cliente.');
        $this->flash->addMessage('error', 'Hubo un problema al actualizar el cliente.');
        return $response->withRedirect($this->router->pathFor('detalle-cliente', [id => $id]));
    }
    $this->flash->addMessage('exito', 'El cliente ha sido actualizado.');
    return $response->withRedirect($this->router->pathFor('detalle-cliente', [id => $id]));
});

$app->post('/nuevo/direccion/{id}', function($request, $response, $args){
    $errores = false;
    $data = $request->getParsedBody();
    $id = filter_var($args['id'], FILTER_SANITIZE_NUMBER_INT);
    // validacion
    if(empty($data['direccion'])){
        $this->flash->addMessage('direccion', 'El campo es requerido');
        $errores = true;
    }
    if(empty($data['calle'])){
        $this->flash->addMessage('calle', 'El campo es requerido');
        $errores = true;
    }
    if(empty($data['colonia'])){
        $this->flash->addMessage('colonia', 'El campo es requerido');
        $errores = true;
    }
    if(empty($data['ciudad'])){
        $this->flash->addMessage('ciudad', 'El campo es requerido');
        $errores = true;
    }
    if(empty($data['estado'])){
        $this->flash->addMessage('estado', 'El campo es requerido');
        $errores = true;
    }
    if(empty($data['pais'])){
        $this->flash->addMessage('pais', 'El campo es requerido');
        $errores = true;
    }
    if(empty($data['cp'])){
        $this->flash->addMessage('cp', 'El campo es requerido');
        $errores = true;
    }
    if(empty($data['telefono'])){
        $this->flash->addMessage('telefono', 'El campo es requerido');
        $errores = true;
    }
    if($errores){
        $this->flash->addMessage('error', 'Tienes que llenar los campos requeridos.');
        return $response->withRedirect($this->router->pathFor('nuevo-direccion', [id => $id]));
    }
    // checar_nuevo_usuario verifica si usuario es unico
    $nuevo_data = [];
    $nuevo_data['cp'] = filter_var($data['cp'], FILTER_SANITIZE_STRING);
    if(!preg_match('/[0-9]{5}/i', $nuevo_data['cp'])){
        $this->flash->addMessage('error', 'Alguna información no tiene formato válido.');
        $this->flash->addMessage('cp', 'El C.P. no tiene un formato válido.');
        $errores = true;
    }
    if($errores){
        return $response->withRedirect($this->router->pathFor('nuevo-direccion', [id => $id]));
    }
    $nuevo_data['direccion'] = filter_var($data['direccion'], FILTER_SANITIZE_STRING);
    $nuevo_data['calle'] = filter_var($data['calle'], FILTER_SANITIZE_STRING);
    $nuevo_data['entre'] = filter_var($data['entre'], FILTER_SANITIZE_STRING);
    $nuevo_data['colonia'] = filter_var($data['colonia'], FILTER_SANITIZE_STRING);
    $nuevo_data['ciudad'] = filter_var($data['ciudad'], FILTER_SANITIZE_STRING);
    $nuevo_data['estado'] = filter_var($data['estado'], FILTER_SANITIZE_STRING);
    $nuevo_data['pais'] = filter_var($data['pais'], FILTER_SANITIZE_STRING);
    $nuevo_data['telefono'] = filter_var($data['telefono'], FILTER_SANITIZE_STRING);
    // direccion_nueva crea la direccion
    $direccion = direccion_nueva($this, $nuevo_data, $id);
    if(empty($direccion)){
        $this->logger->addInfo('Fallo creación de nueva dirección.');
        $this->flash->addMessage('error', 'Hubo un problema al crear la dirección.');
        return $response->withRedirect($this->router->pathFor('detalle-cliente', [id => $id]));
    }
    $this->flash->addMessage('exito', 'La dirección ha sido creada.');
    return $response->withRedirect($this->router->pathFor('detalle-cliente', [id => $id]));
});

$app->post('/nuevo/entrega/{id}', function($request, $response, $args){
    $errores = false;
    $destinatario_dir = false;
    $remitente_dir = false;
    $data = $request->getParsedBody();
    $id = filter_var($args['id'], FILTER_SANITIZE_NUMBER_INT);
    // validacion
    if(empty($data['destinatario_nombre'])){
        $this->flash->addMessage('destinatario_nombre', 'El campo es requerido');
        $errores = true;
    }
    if(empty($data['destinatario_razon'])){
        $this->flash->addMessage('destinatario_razon', 'El campo es requerido');
        $errores = true;
    }
    // direcciones
    if(empty($data['destinatario_dir'])){
        $destinatario_dir = true;
        // validacion destinatario
        if(empty($data['d_direccion'])){
            $this->flash->addMessage('d_direccion', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['d_calle'])){
            $this->flash->addMessage('d_calle', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['d_colonia'])){
            $this->flash->addMessage('d_colonia', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['d_ciudad'])){
            $this->flash->addMessage('d_ciudad', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['d_estado'])){
            $this->flash->addMessage('d_estado', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['d_pais'])){
            $this->flash->addMessage('d_pais', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['d_cp'])){
            $this->flash->addMessage('d_cp', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['d_telefono'])){
            $this->flash->addMessage('d_telefono', 'El campo es requerido');
            $errores = true;
        }
    }
    if(empty($data['remitente_dir'])){
        $remitente_dir = true;
        // validacion remitente
        if(empty($data['r_direccion'])){
            $this->flash->addMessage('r_direccion', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['r_calle'])){
            $this->flash->addMessage('r_calle', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['r_colonia'])){
            $this->flash->addMessage('r_colonia', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['r_ciudad'])){
            $this->flash->addMessage('r_ciudad', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['r_estado'])){
            $this->flash->addMessage('r_estado', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['r_pais'])){
            $this->flash->addMessage('r_pais', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['r_cp'])){
            $this->flash->addMessage('r_cp', 'El campo es requerido');
            $errores = true;
        }
        if(empty($data['r_telefono'])){
            $this->flash->addMessage('r_telefono', 'El campo es requerido');
            $errores = true;
        }
    }
    if($errores){
        $this->flash->addMessage('error', 'Tienes que llenar los campos requeridos.');
        return $response->withRedirect($this->router->pathFor('nuevo-entrega', [id => $id]));
    }
    //
    $nuevo_data = [];
    // destinatario crear
    if($destinatario_dir){
        $nueva_dir_data = [];
        $nueva_dir_data['cp'] = filter_var($data['d_cp'], FILTER_SANITIZE_STRING);
        if(!preg_match('/[0-9]{5}/i', $nueva_dir_data['cp'])){
            $this->flash->addMessage('error', 'Alguna información no tiene formato válido.');
            $this->flash->addMessage('d_cp', 'El C.P. no tiene un formato válido.');
            $errores = true;
        }
        if($errores){
            return $response->withRedirect($this->router->pathFor('nuevo-entrega', [id => $id]));
        }
        $nueva_dir_data['direccion'] = filter_var($data['d_direccion'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['calle'] = filter_var($data['d_calle'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['entre'] = filter_var($data['d_entre'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['colonia'] = filter_var($data['d_colonia'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['ciudad'] = filter_var($data['d_ciudad'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['estado'] = filter_var($data['d_estado'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['pais'] = filter_var($data['d_pais'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['telefono'] = filter_var($data['d_telefono'], FILTER_SANITIZE_STRING);
        // direccion_nueva crea la direccion
        $direccion = direccion_nueva($this, $nueva_dir_data, $id);
        if(empty($direccion)){
            $this->logger->addInfo('Fallo creación de nueva dirección.');
            $this->flash->addMessage('error', 'Hubo un problema al crear la dirección.');
            return $response->withRedirect($this->router->pathFor('detalle-cliente', [id => $id]));
        }
        $nuevo_data['destinatario_dir'] = $direccion['id'];
    }else{
        $nuevo_data['destinatario_dir'] = filter_var($data['destinatario_dir'], FILTER_SANITIZE_NUMBER_INT);
    }
    // remitente crear
    if($remitente_dir){
        $nueva_dir_data = [];
        $nueva_dir_data['cp'] = filter_var($data['r_cp'], FILTER_SANITIZE_STRING);
        if(!preg_match('/[0-9]{5}/i', $nueva_dir_data['cp'])){
            $this->flash->addMessage('error', 'Alguna información no tiene formato válido.');
            $this->flash->addMessage('r_cp', 'El C.P. no tiene un formato válido.');
            $errores = true;
        }
        if($errores){
            return $response->withRedirect($this->router->pathFor('nuevo-entrega', [id => $id]));
        }
        $nueva_dir_data['direccion'] = filter_var($data['r_direccion'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['calle'] = filter_var($data['r_calle'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['entre'] = filter_var($data['r_entre'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['colonia'] = filter_var($data['r_colonia'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['ciudad'] = filter_var($data['r_ciudad'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['estado'] = filter_var($data['r_estado'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['pais'] = filter_var($data['r_pais'], FILTER_SANITIZE_STRING);
        $nueva_dir_data['telefono'] = filter_var($data['r_telefono'], FILTER_SANITIZE_STRING);
        // direccion_nueva crea la direccion
        $direccion = direccion_nueva($this, $nueva_dir_data, $id);
        if(empty($direccion)){
            $this->logger->addInfo('Fallo creación de nueva dirección.');
            $this->flash->addMessage('error', 'Hubo un problema al crear la dirección.');
            return $response->withRedirect($this->router->pathFor('detalle-cliente', [id => $id]));
        }
        $nuevo_data['remitente_dir'] = $direccion['id'];
    }else{
        $nuevo_data['remitente_dir'] = filter_var($data['remitente_dir'], FILTER_SANITIZE_NUMBER_INT);
    }
    // checar_nuevo_usuario verifica si usuario es unico
    $nuevo_data['destinatario_nombre'] = filter_var($data['destinatario_nombre'], FILTER_SANITIZE_STRING);
    $nuevo_data['destinatario_razon'] = filter_var($data['destinatario_razon'], FILTER_SANITIZE_STRING);
    $nuevo_data['tipo_entrega_id'] = filter_var($data['tipo_entrega_id'], FILTER_SANITIZE_NUMBER_INT);
    $nuevo_data['acuse'] = filter_var($data['acuse'], FILTER_VALIDATE_BOOLEAN);
    $nuevo_data['cobertura_id'] = filter_var($data['cobertura_id'], FILTER_SANITIZE_NUMBER_INT);
    $nuevo_data['cod'] = filter_var($data['cod'], FILTER_VALIDATE_BOOLEAN);
    $nuevo_data['cantidad'] = filter_var($data['cantidad'], FILTER_SANITIZE_NUMBER_INT);
    $nuevo_data['peso'] = filter_var($data['peso'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    // entrega_nueva crea la entrega
    $entrega = entrega_nueva($this, $nuevo_data, $id);
    if(empty($entrega)){
        $this->logger->addInfo('Fallo creación de nueva entrega.');
        $this->flash->addMessage('error', 'Hubo un problema al crear la entrega.');
        return $response->withRedirect($this->router->pathFor('detalle-cliente', [id => $id]));
    }
    $this->flash->addMessage('exito', 'La entrega ha sido creada.');
    return $response->withRedirect($this->router->pathFor('detalle-cliente', [id => $id]));
});

$app->run();

/*================================================ DB Model ==================================================*/
function demo_keys($c){
    try{
        $results = $c['db']->query("
            SELECT *
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

function clientes_lista($c){
    try{
        //echo var_dump($c); exit();
        $results = $c['db']->query("
            SELECT clientes.*, usuarios.email
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
            SELECT clientes.*, usuarios.email
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
        $results2 = $c['db']->prepare("
            INSERT INTO clientes
            (nombre, razon, usuario_id)
            VALUES
            (?, ?, ?)
        ");
        $results2->bindParam(1, $data['nombre']);
        $results2->bindParam(2, $data['razon']);
        $results2->bindValue(3, $id, PDO::PARAM_INT);
        $results2->execute();
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
    if(array_key_exists('razon', $data)){
        try{
            $results = $c['db']->prepare("
                UPDATE clientes SET
                razon = ?
                WHERE usuario_id = ?
            ");
            $results->bindParam(1, $data['razon']);
            $results->bindValue(2, $id, PDO::PARAM_INT);
            $results->execute();
        }catch(Exception $e){
            echo ('No se pudo leer la informacion de la base de datos');
            exit();
        }
    }
    return true;
}

function tipos_entrega_lista($c){
    try{
        //echo var_dump($id); exit();
        $results = $c['db']->query("
            SELECT *
            FROM `tipos entrega`
        ");
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    $tipos_entrega = $results->fetchAll();
    return $tipos_entrega;
}

function coberturas_lista($c){
    try{
        //echo var_dump($id); exit();
        $results = $c['db']->query("
            SELECT *
            FROM coberturas
        ");
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    $coberturas = $results->fetchAll();
    return $coberturas;
}

function estados_lista($c){
    try{
        //echo var_dump($id); exit();
        $results = $c['db']->query("
            SELECT *
            FROM estados
        ");
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    $estados = $results->fetchAll();
    return $estados;
}

function direccion_detalle($c, $id){
    try{
        $results = $c['db']->prepare("
            SELECT *
            FROM direcciones
            WHERE id = ?
        ");
        $results->bindValue(1, $id, PDO::PARAM_INT);
        $results->execute();
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    return $results->fetch();
}

function direcciones_lista($c, $id){
    try{
        //echo var_dump($id); exit();
        $results = $c['db']->prepare("
            SELECT *
            FROM direcciones
            WHERE usuario_id = ?
        ");
        $results->bindValue(1, $id, PDO::PARAM_INT);
        $results->execute();
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    $direcciones = $results->fetchAll();
    return $direcciones;
}

function direccion_nueva($c, $data, $id){
    try{
        $results = $c['db']->prepare("
            INSERT INTO direcciones
            (direccion, calle, entre, colonia, ciudad,    estado, pais, cp, telefono, usuario_id)
            VALUES
            (?, ?, ?, ?, ?,    ?, ?, ?, ?, ?)
        ");
        $results->bindParam(1, $data['direccion']);
        $results->bindParam(2, $data['calle']);
        $results->bindParam(3, $data['entre']);
        $results->bindParam(4, $data['colonia']);
        $results->bindParam(5, $data['ciudad']);

        $results->bindParam(6, $data['estado']);
        $results->bindParam(7, $data['pais']);
        $results->bindParam(8, $data['cp']);
        $results->bindParam(9, $data['telefono']);
        $results->bindValue(10, $id, PDO::PARAM_INT);
        $results->execute();
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    return $c['db']->lastInsertId();
}

function entregas_lista($c, $id){
    try{
        //echo var_dump($id); exit();
        $results = $c['db']->prepare("
            SELECT *
            FROM entregas
            WHERE usuario_id = ?
        ");
        $results->bindValue(1, $id, PDO::PARAM_INT);
        $results->execute();
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    $entregas = $results->fetchAll();
    return $entregas;
}

function entrega_detalle($c, $id){
    try{
        $results = $c['db']->prepare("
            SELECT e.*, t.tipo_entrega, c.cobertura, es.estado
            FROM entregas AS e
                LEFT JOIN `tipos entrega` AS t ON (e.tipo_entrega_id = t.id)
                LEFT JOIN coberturas AS c ON (e.cobertura_id = c.id)
                LEFT JOIN estados AS es ON (e.estado_id = es.id)
            WHERE e.id = ?
        ");
        $results->bindValue(1, $id, PDO::PARAM_INT);
        $results->execute();
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos' . $e);
        exit();
    }
    $results = $results->fetch();
    //echo var_dump($results); exit();
    return $results;
}

function entrega_nueva($c, $data, $id){
    try{
        //var_dump($data); exit();
        $results = $c['db']->prepare("
            INSERT INTO entregas
            (destinatario_nombre, destinatario_razon, destinatario_dir, remitente_dir, tipo_entrega_id,
            acuse, cobertura_id, cod, cantidad, peso,
            estado_id, usuario_id)
            VALUES
            (?, ?, ?, ?, ?,    ?, ?, ?, ?, ?,   1,?)
        ");
        $results->bindParam(1, $data['destinatario_nombre']);
        $results->bindParam(2, $data['destinatario_razon']);
        $results->bindValue(3, $data['destinatario_dir'], PDO::PARAM_INT);
        $results->bindValue(4, $data['remitente_dir'], PDO::PARAM_INT);
        $results->bindValue(5, $data['tipo_entrega_id'], PDO::PARAM_INT);

        $results->bindValue(6, $data['acuse'], PDO::PARAM_BOOL);
        $results->bindValue(7, $data['cobertura_id'], PDO::PARAM_INT);
        $results->bindValue(8, $data['cod'], PDO::PARAM_BOOL);
        $results->bindValue(9, $data['cantidad'], PDO::PARAM_INT);
        $results->bindValue(10, $data['peso']);

        $results->bindValue(11, $id, PDO::PARAM_INT);
        $results->execute();
    }catch(Exception $e){
        echo ('No se pudo leer la informacion de la base de datos');
        exit();
    }
    return true;
}