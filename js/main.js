/*************** main.js ***************/
$(document).ready(function(){
	// form-login
	$input_usuario = $("input[name='usuario']");
	$input_clave = $("input[name='clave']");
	$('.accion-login-llenar').on('mousedown touchstart', function(event){
		event.preventDefault();
		$input_usuario.get(0).value = $(this).attr('data-usuario');
		$input_clave.get(0).value = $(this).attr('data-clave');
		return false;
	});
	//mensajes
	var mensajes = [];
	$('.mensajes').children().each(function(index){
		$(this).show(500);
		// var mensaje = $(this).css({'opacity': '0'}).hide();
		// mensaje.altura = mensaje.height();
		// mensaje.height(1).hide();
		// mensajes.push(mensaje);
	});
	// vars
	var actividad = $('body');
	var colores = {'normal': 'none', 'correcta': '#129af0', 'incorrecta': '#999999'};
	var feeds = {};
	var inputs = [];
	var posiciones = [];
	var estaPieza;
	var contador;
	var stage;
	var paper;
	
	// preload
	var preloader;
	var manifest = [/*{src:'img/mascot.png', id:'mascot'},
					{src:'img/restart.png', id:'restart'}*/];
	
	// UI inicializacion de los elementos de los elementos interactivos
	var canvas = $('<canvas width="790" height="560"></canvas>').css({'position': 'absolute', 'left': '0px', 'top': '0px', 'z-index': '-1'});
	var svg = $('<svg width="790" height="560"></svg>').css({'position': 'absolute', 'left': '0px', 'top': '0px', 'z-index': '-1'});
	var input_vacio = $('<input type="text" size="1">').css({'opacity': '0'}).on('keydown', limit_tab).appendTo(actividad).hide();
	actividad.find('.fondo').on('mousedown touchstart', prevent);
	
	
	// botones
	var boton_cerrar = actividad.find('.cerrar').on('mousedown touchstart', cerrar_pres);
	var boton_info = actividad.find('.masinfo').css({'cursor': 'pointer'}).on('mousedown touchstart', info_pres).hide();

	// feeds
	actividad.find('.feed, .feed_final').each(function(index){
		var feed = $(this);
		var nombre = (feed.attr('class')).substr(5);
		if(nombre == '' || nombre == null) nombre = 'comun';
		feed.attr('nombre', nombre).css({'opacity': '0'}).on('mousedown touchstart', prevent).hide();
		feed.boton = feed.find('.cerrar_feed, .cerrar_feed_final').on('mousedown touchstart', close_feed);
		feed.texto = feed.find('.txt_feed');
		feeds[nombre] = feed;
    });
	var feeds_correctas = ['¡Muy bien!  Esa es la inversa de la función inicial.',
					   	   'Rectifica nuevamente tus  sustituciones para obtener la información que se te solicita.',
					   	   'Recuerda que una función es una relación entre un conjunto dado X (el dominio) y otro conjunto de elementos Y (el codominio) de forma que a cada elemento del dominio le corresponde un único elemento del codominio. Por lo tanto esta si es una función ya que a cada elemento de t le corresponde un valor de v.',
					   	   'Rectifica nuevamente tu despeje para obtener la función inversa.'];
	var feeds_incorrectas = ['',
							 ''];
	
	// main
	init_canvas();
	preload();
	
/************** Funciones **************/

	function init_canvas(){		//Inicializa el <canvas> y guarda sus dimensiones para futuras referencias
		actividad.append(canvas);
		canvas.ancho = parseInt(canvas.attr('width'));
		canvas.alto = parseInt(canvas.attr('height'));
		canvas.centro = {x: canvas.ancho/2, y: canvas.alto/2};
		stage = new createjs.Stage(canvas.get(0));
		createjs.Ticker.setFPS(30);
		//
		actividad.append(svg);
		paper = Snap(svg.get(0));
	};
	
	function preload(){		//pre-carga las imagenes
		if(manifest.length == 0){
			init();
			return;
		};
		preloader = new createjs.LoadQueue();
		preloader.installPlugin(createjs.Sound);
		if(document.location.protocol == 'file:'){
			preloader.preferXHR = false;
		};
		var texto = new createjs.Text('', '20px Arial', '#666');
		texto.textAlign = 'center';
		texto.x = canvas.centro.x;
		texto.y = canvas.centro.y;
		stage.addChild(texto);
		
		preloader.on('complete', function(){
			preloader.removeEventListener('tick');
			stage.removeChild(texto);
			stage.update();
			init();
		})
		createjs.Ticker.addEventListener('tick', function(){
			var cargado = Math.floor(preloader.progress * 100);
			texto.text = 'Cargando ' + cargado + ' %';
			stage.update();
		});
		preloader.loadManifest(manifest);
	};
	
	function init(){	//Rutina principal, prepara los elementos y asigna los eventos de la actividad
		/*var PUBNUB_demo = PUBNUB.init({
			publish_key: 'pub-c-66a524ed-104c-4529-bdc9-4c3e7842b060',
			subscribe_key: 'sub-c-6af197e4-7470-11e6-b0c8-02ee2ddab7fe'
		});
		PUBNUB_demo.subscribe({
			channel: 'demo_tutorial',
			message: function(m){console.log(m)},
			connect : publish

		});
		console.log('hola');

		function publish() {
          PUBNUB_demo.publish({
            channel: 'demo_tutorial',
            message: {"text":"Hey!"}
          });
        }*/



		/*bungee = SVG.select('#bungee');
		paper.append(bungee);
		bungee.attr({fill: '#fff'});
		mono = bungee.g(bungee.select('#b-mono'));
		estructura = bungee.select('#b-estructura').attr({opacity: .75});
		estructura.selectAll('rect, polygon').attr({'fill': '#fff'});
		plataforma = bungee.select('#b-plataforma').attr({opacity: .75});
		plataforma.selectAll('rect, polygon').attr({'fill': '#fff'});
		contrapeso = bungee.select('#b-contrapeso').attr({opacity: .75});
		contrapeso.selectAll('rect, polygon').attr({'fill': '#fff'});
		//
		bungee.origen = {x: 568, y: 72};
		//console.log(mono.getBBox());
		mono.punto = mono.circle(bungee.origen.x, bungee.origen.y, 3).attr({opacity: 0});
		bungee.linea = bungee.path().attr({fill: 'none', stroke: '#fff'})/*.transform('t' + origen.x + ',' + origen.y)*/;
		/*base = bungee.select('#t-base');
		base.selectAll('line, rect').attr({'stroke': '#fff'});
		sillas = bungee.select('#t-sillas');
		pedazos = sillas.selectAll('polygon, path');*/
		re_init();
	};
	
	function re_init(){	//Rutina principal, prepara los elementos y asigna los eventos de la actividad
		
	// $.each(mensajes, function(index){
	// 	this.show()/*.velocity({'height': this.altura + 'px', 'opacity': 1}, 600)*/;
	// });
		actividad.correcta = false;
		$.each(feeds, function(key){
			this.css({'opacity': 0}).hide();
			this.boton.css({'cursor': 'pointer'}).off('mousedown touchstart').on('mousedown touchstart', close_feed);
		});
		//
		posiciones = random_array(posiciones);
		/*$.each(inputs, function(i){
			this.css({'opacity': '0', 'left': posiciones[i].x + 'px', 'top': posiciones[i].y + 'px'}).hide();
			this.css({'cursor': 'default'}).off('mousedown touchstart').on('mousedown touchstart', prevent);
			this.children().css({'cursor': 'default'});
			this.marca.hide();
			this.indice_pregunta = posiciones[i].indice;
		});
		instruccion.css({'opacity': '0'}).hide();
		boton_info.css({'opacity': '0'}).hide();
		boton_info.img.velocity({'rotateZ': '0 deg'}, 1);
		rect.css({'opacity': '0'}).hide();
		meme.css({'opacity': '0'}).hide();
		bungee.transform('t85,90s.8,.8,0,0');
		mono.transform('t0,0r0');
		bungee.linea.attr({d: 'M' + bungee.origen.x + ',' + bungee.origen.y + 'L' + (bungee.origen.x) + ',' + (bungee.origen.y)});
		/*$.each(pedazos, function(i){
			this.transform('t0,0r0');
		});*/
	};
	
	function comenzar(){	//Rutina principal, prepara los elementos y asigna los eventos de la actividad
		if(actividad.correcta) return;
		actividad.timer = setTimeout(function(){
			sonidos['p3_audio'].loop().play();
			actividad.anim = setInterval(animar_linea, 30);
			mono.animate({transform: 't10,180r180'}, 3000, mina.easeout,function(){
				sonidos['p3_audio'].stop();
				clearInterval(actividad.anim);
				animar_linea();
				boton_info.show().velocity({'opacity': '1'}, 1200);
				rect.show().velocity({'opacity': '1'}, 1200);
				$.each(inputs, function(i){
					this.css({'opacity': '1'}).show('slide', {duration: (1000 + 300 * this.indice_pregunta), direction: 'right'});
				});
				bungee.animate({transform: 't-34,165s.6,.6,0,0'}, 1200, function(){
					$.each(inputs, function(i){
						this.css({'cursor': 'pointer'}).off('mousedown touchstart').on('mousedown touchstart', input_pres);
						this.children().css({'cursor': 'pointer'});
					});
				});
			});
		}, 1000);
	};
	
	function detener(){	//Rutina principal, prepara los elementos y asigna los eventos de la actividad
		clearInterval(actividad.anim);
		clearTimeout(actividad.timer);
		buzz.all().stop();
		mono.stop();
		bungee.stop();
		$.each(inputs, function(key){
			this.stop(true, true);
		});
		boton_info.img.velocity('stop');
		$.each(feeds, function(key){
			this.velocity('stop');
		});
	};
	
	function animar_linea(){ //animaciones basadas en tiempo
		//var pos_mono = mono.punto.node.getBoundingClientRect();
		var p = paper.node.createSVGPoint();
		var m = mono.node.getTransformToElement(bungee.node);
		p.x = bungee.origen.x;
		p.y = bungee.origen.y;
		p = p.matrixTransform(m);
		bungee.linea.attr({d: 'M' + bungee.origen.x + ',' + bungee.origen.y + 'L' + (p.x) + ',' + (p.y)});
		//console.log(p.x + ' - ' + p.y);
	};
	
	function animar(){ //animaciones basadas en tiempo
		stage.update();
	};
	
	function close_feed(event){	//el usuario cerró una ventana emergente
		event.preventDefault();
		var feed = feeds[$(this).parent().attr('nombre')];
		feed.boton.css({'cursor': 'default'}).off('mousedown touchstart').on('mousedown touchstart', prevent);
		feed.hide(feed_efecto, 1000, function(){
			feed.boton.css({'cursor': 'pointer'}).off('mousedown touchstart').on('mousedown touchstart', close_feed);
			feed.hide();
			if(actividad.correcta){
				ir_a(0, 'slide', {dir: -1});
				return false;
			}else{
				$.each(inputs, function(i){
					this.css({'cursor': 'pointer'}).off('mousedown touchstart').on('mousedown touchstart', input_pres);
					this.children().css({'cursor': 'pointer'});
				});
				mono.transform('t0,180r180');
				animar_linea();
				/*$.each(pedazos, function(i){
					this.transform('t0,0r0');
				});*/
			};
		});
		return false;
	};
	
	function input_pres(event){
		event.preventDefault();
		var input = inputs[parseInt($(this).attr('indice'))];
		$.each(inputs, function(i){
			this.css({'cursor': 'default'}).off('mousedown touchstart').on('mousedown touchstart', prevent);
			this.children().css({'cursor': 'default'});
		});
		//feed
		var feed = feeds['comun'];
		feed.css({'margin': '0px', 'left': (canvas.centro.x - feed.width()/2) + 'px', 'top': (canvas.centro.y - feed.height()/2) + 'px'});
		feed.texto.html(feeds_correctas[input.indice]);
		if(input.indice == 0){
			actividad.correcta = true;
			sonidos['correcta'].play();
			actividad.anim = setInterval(animar_linea, 30);
			pantallas[0].menu[actividad.indice - 1].marca.show();
			pantallas[0].menu[actividad.indice - 1].correcta = true;
			input.marca.show(600);
			mono.animate({transform: 't-10,360r180'}, 6000, mina.elastic, function(){
				feed.css({'opacity': '1'}).show(feed_efecto, 1000);
				clearInterval(actividad.anim);
			});
		}else{
			actividad.correcta = false;
			sonidos['incorrecta'].play();
			bungee.linea.attr({d: 'M' + bungee.origen.x + ',' + bungee.origen.y + 'L' + (bungee.origen.x) + ',' + (bungee.origen.y)});
			mono.animate({transform: 't-10,470r90'}, 4000, mina.bounce, function(){
				feed.css({'opacity': '1'}).show(feed_efecto, 1000);
				//clearInterval(actividad.anim);
			});
		};
		return false;
	};
	
	function info_pres(event){
		event.preventDefault();
		if(instruccion.visible){
			instruccion.visible = false;
			instruccion.hide('blind', 1200);
			boton_info.img.velocity({'rotateZ': '0 deg'}, 1200, function(){
				$.each(inputs, function(i){
					this.css({'cursor': 'pointer'}).off('mousedown touchstart').on('mousedown touchstart', input_pres);
					this.children().css({'cursor': 'pointer'});
				});
			});
		}else{
			instruccion.visible = true;
			$.each(inputs, function(i){
				this.css({'cursor': 'default'}).off('mousedown touchstart').on('mousedown touchstart', prevent);
				this.children().css({'cursor': 'default'});
			});
			instruccion.css({'opacity': '1'}).show('blind', 1200);
			boton_info.img.velocity({'rotateZ': '135 deg'}, 1200);
		};
		return false;
	};
	
	function cerrar_pres(event){
		event.preventDefault();
		ir_a(0, 'slide', {dir: -1});
		return false;
	};
		
//************** Funciones Carcasa **************/
// llamadas desde afuera
	
	actividad.terminar = function (){	//se va a dejar esta pantalla
		//console.log('terminar - pantalla ' + estaActividad);
		detener();
	};
	
	actividad.preparar = function (){	//se prepara esta pantalla para mostrar
		//console.log('preparar - pantalla ' + estaActividad);
		re_init();
	};
	
	actividad.comenzar = function (){	//termino de aparecer la pantalla
		//console.log('comenzar - pantalla ' + estaActividad);
		comenzar();
	};
	
	actividad.comprobar = function (){	//el usuario hace click en el boton comprobar
		//console.log('comprobar - pantalla ' + estaActividad);
	};
	
	actividad.reiniciar = function (){	//el usuario hace click en el boton reiniciar
		//console.log('reiniciar - pantalla ' + estaActividad);
		detener();
		re_init();
		comenzar();
	};
	
});		//Fin de script