<?php

/******************************************************
*							Socket server														*
******************************************************/

if(isset($INI)&&isset($INI['SocketServer']))$WSS_INI=$INI['SocketServer'];
elseif(file_exists(__DIR__.'/settings.ini'))$WSS_INI=parse_ini_file(__DIR__.'/settings.ini');
else $WSS_INI=array();
$WSS_INI['Interface']=!isset($WSS_INI['Interface'])||$WSS_INI['Interface']=='*'||strtolower($WSS_INI['Interface'])=='all' ? '0.0.0.0' : $WSS_INI['Interface'];
$WSS_INI['Port']=isset($WSS_INI['Port']) ? $WSS_INI['Port'] : '8000';

$SOCKET=stream_socket_server("tcp://".$WSS_INI['Interface'].":".$WSS_INI['Port'],$errno,$errstr);
if(!$SOCKET)die("Can't create server socket. Error: $errstr ($errno)\r\n");
$CONNECTS_DATA=array();
$CONNECTS=array();
$RECEIVERS=array();

//************ main cycle ****************************

while (true) {
	$read=$CONNECTS;
	$read[]=$SOCKET;
	$write=$RECEIVERS;
	$except=null;
echo('Waiting.....  ');
	if(stream_select($read,$write,$except,null)===false)break;
echo('Gotcha! '.count($read));
if(count($read))echo("($read[0]");
echo(', '.count($write));
if(count($write))echo("($write[0])");
echo("\r\n");
	if(in_array($SOCKET,$read)){
		$CONNECTS[]=stream_socket_accept($SOCKET,-1);
		unset($read[array_search($SOCKET,$read)]);
	}
	foreach($read as $connect) {
		if(strlen($str=fread($connect,10000000))==0||!rawDataReceiveHandler($connect,$str)){
			stream_socket_shutdown($connect, STREAM_SHUT_WR);
			unset($CONNECTS[array_search($connect,$CONNECTS)]); 
			unset($RECEIVERS[array_search($connect,$RECEIVERS)]); 
			unset($CONNECTS_DATA[(string)$connect]);
			onClose($connect);
		}
		unset($read[array_search($connect,$read)]); 
	}
	foreach($write as $connect){
		if(!isset($CONNECTS_DATA[(string)$connect])||strlen($CONNECTS_DATA[(string)$connect]['WriteBuffer'])==0)unset($RECEIVERS[array_search($connect,$RECEIVERS)]);
		else{
			$transmitted=fwrite($connect,$CONNECTS_DATA[(string)$connect]['WriteBuffer']);
			if($transmitted==false){
				stream_socket_shutdown($connect, STREAM_SHUT_WR);
				unset($CONNECTS[array_search($connect,$CONNECTS)]); 
				onClose($connect);
			}else{
				if($transmitted < strlen($CONNECTS_DATA[(string)$connect]['WriteBuffer'])){
					$CONNECTS_DATA[(string)$connect]['WriteBuffer']=substr($CONNECTS_DATA[(string)$connect]['WriteBuffer'],$transmitted);
					continue;
				}
				$CONNECTS_DATA[(string)$connect]['WriteBuffer']='';
			}
			unset($RECEIVERS[array_search($connect,$RECEIVERS)]); 
		} 
		unset($write[array_search($connect,$write)]); 
	}
}
die("stream_select failed!");


//********************* functions *********************

function rawDataReceiveHandler($con,$str){
	global $CONNECTS_DATA,$RECEIVERS;
	$idx=(string)$con;
	if(!isset($CONNECTS_DATA[$idx]))$CONNECTS_DATA[$idx]=array('Connected'=>false,'Resource'=> $con,'WriteBuffer'=>'','ReadBuffer'=> $str);
	else $CONNECTS_DATA[$idx]['ReadBuffer'].=$str;
	if(!$CONNECTS_DATA[$idx]['Connected']){
		if(isset($CONNECTS_DATA[$idx]['Info']['Protocol']))$CONNECTS_DATA[$idx]['Info']=parseReplyToProtocolHandshake($CONNECTS_DATA[$idx]['ReadBuffer']);	//read buffer transferred by reference  ??????????
		else $CONNECTS_DATA[$idx]['Info']=parseProtocolHandshake($CONNECTS_DATA[$idx]['ReadBuffer']);	//read buffer transferred by reference
		if($CONNECTS_DATA[$idx]['Info']===false)return false;
		if(count($CONNECTS_DATA[$idx]['Info']) > 0){
			onConnect($CONNECTS_DATA[$idx]['Info'],$con);
			$CONNECTS_DATA[$idx]['Connected']=true;
			if($CONNECTS_DATA[$idx]['Info']['Protocol']=='websocket server'){
				$CONNECTS_DATA[$idx]['WriteBuffer']=replyToProtocolHandshake($CONNECTS_DATA[$idx]['Info']);
				$RECEIVERS[]=$con;
				return true;
			}
		}else return true;
	}
	while(strlen($CONNECTS_DATA[$idx]['ReadBuffer'])>0){
		$frm=parseInput($CONNECTS_DATA[$idx]['ReadBuffer'],$CONNECTS_DATA[$idx]['Info']['Protocol']);		//read buffer transferred by reference, parseInput returns array of messages;
		if($frm===false)break;		//frame is not complited yet.
		if(!is_array($frm)){onError($frm,$con);return false;}			//error ($frm is error message). Notify high-level proc. and close connection;
		if($rply=procFrame($frm,$con)){
			$rply=composeOutput($rply,$CONNECTS_DATA[$idx]['Info']['Protocol']);
			if(is_array($rply)){onError($rply['error'],$con);return false;}	//error ($rply is not string). Notify high-level proc. and close connection;
			$CONNECTS_DATA[$idx]['WriteBuffer'].=$rply;
		}
	}
	if($CONNECTS_DATA[$idx]['WriteBuffer']&&!in_array($con,$RECEIVERS))$RECEIVERS[]=$con;
	return true;
}

function parseProtocolHandshake(&$msg){
	$msg=ltrim($msg);
	if(substr($msg,0,3)=='GET'){		//check for web socket protocol
		if(substr($msg,-4)!="\r\n\r\n")return array();	//handshake message not completed 
		$sep="\r\n";
		$info=array();
		$headers=explode($sep,rtrim($msg));
		$header=explode(' ', $headers[0]);
		$info['method'] = $header[0];
		$info['url'] = $header[1];
		for($i=1;$i < count($headers);$i++){
			$line=rtrim($headers[$i]);
			if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))$info[$matches[1]]=$matches[2];
		}
		if(!isset($info['Upgrade'])||strToLower($info['Upgrade'])!='websocket'||empty($info['Sec-WebSocket-Key']))return false;	//more checking can be added here		
		$info['Protocol']='websocket server';
		$msg='';
		return $info;
	}
	return array('Protocol'=>'raw');	//raw socket assumed;
}

function replyToProtocolHandshake($inf){
	$SecWebSocketAccept=base64_encode(pack('H*',sha1($inf['Sec-WebSocket-Key'].'258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
	$upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
		"Upgrade: websocket\r\n" .
		"Connection: Upgrade\r\n" .
		"Sec-WebSocket-Accept: $SecWebSocketAccept\r\n\r\n";
	return $upgrade;
}

function parseReplyToProtocolHandshake(&$msg){
	$msg=ltrim($msg);
	$info=array();
	$lim=strpos($msg,"\r\n\r\n");
	if(!$lim)return array(); 
	$headers=explode("\r\n",rtrim($msg));
	$respcode=explode(" ",$headers[0]);
	$respcode=$respcode[1];
	for($i=1;$i < count($headers);$i++){
		$line=rtrim($headers[$i]);
		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))$info[$matches[1]]=$matches[2];
	}
	if(!isset($info['Upgrade'])||strToLower($info['Upgrade'])!='websocket'||$info['Sec-WebSocket-Accept']!='Wwi5YhN+AEPMSbD/K0A4DFneKlU=')return false;	//more checking can be added here	
	$info['Protocol']='websocket client';
	$msg=substr($msg,$lim+8);
	return $info;	
}
function parseInput(&$buf,$prot){
	$ret=array();
	if($prot=='raw'){$ret['data']=$buf;$buf='';return $ret;}
	if(substr($prot,0,9)=='websocket'){
		$unmaskedPayload='';
		$decodedData=array();
		$firstByteBinary=sprintf('%08b',ord($buf[0]));		//get frame info.
		$secondByteBinary=sprintf('%08b',ord($buf[1]));
		$decodedData['final']=($firstByteBinary[0]=='1') ? true : false;		//final frame?
		$opcode=bindec(substr($firstByteBinary,4,4));			// estimate frame type:
		switch($opcode){
			case 1: $decodedData['type']='text';break;
			case 2: $decodedData['type']='binary';break;
			case 8: $decodedData['type']='close';break;			// connection close frame
			case 9: $decodedData['type']='ping';break;			// ping frame
			case 10: $decodedData['type']='pong';break;			// pong frame
			default: return 'unknown opcode (1003)';
		}
		$isMasked=($secondByteBinary[0]=='1') ? true : false;
		if(!$isMasked&&$prot=='websocket server')return 'protocol error (1002)';			// unmasked frame is received by server
		if($isMasked&&$prot=='websocket client')return 'protocol error (1002)';			// masked frame is received by client
		$payloadLength=ord($buf[1])&127;
		if($payloadLength===126){
			$mask=substr($buf, 4, 4);
			$payloadOffset=8;
			$dataLength=bindec(sprintf('%08b', ord($buf[2])).sprintf('%08b',ord($buf[3])))+$payloadOffset;
		}elseif($payloadLength===127){
			$mask=substr($buf,10,4);
			$payloadOffset=14;
			$tmp = '';
			for($i = 0;$i < 8;$i++)$tmp.=sprintf('%08b',ord($buf[$i+2]));
			$dataLength=bindec($tmp)+$payloadOffset;
			unset($tmp);
    }else{
			$mask=substr($buf,2,4);
			$payloadOffset=6;
			$dataLength=$payloadLength+$payloadOffset;
		}
		if(strlen($buf) < $dataLength)return false;			//frame is not complited yet
		if($isMasked){
			for($i=$payloadOffset;$i < $dataLength;$i++){
				$j=$i-$payloadOffset;
				if(isset($buf[$i]))$unmaskedPayload.=$buf[$i]^$mask[$j%4];
			}
			$decodedData['data']=$unmaskedPayload;
    }else{
			$payloadOffset=$payloadOffset-4;
			$decodedData['data']=substr($buf,$payloadOffset);
		}
		$buf=substr($buf,$dataLength);
    return $decodedData;
	}
}

function composeOutput($msg,$prot){
	if($prot=='raw'){
		if(is_array($msg))return $msg['data'];
		return $msg;
	}
	if(substr($prot,0,9)=='websocket'){
		$type=is_array($msg) ? $msg['type'] :'text';
		$masked=strpos($prot,'server') ? false : true;
		$payload=is_array($msg) ? $msg['data'] : $msg;
		$frameHead=array();
		$payloadLength=strlen($payload);
		switch($type){
			case 'text': $frameHead[0]=129;break;				// first byte indicates FIN, Text-Frame (10000001):
			case 'close': $frameHead[0] = 136;break;		// first byte indicates FIN, Close Frame(10001000):
			case 'ping': $frameHead[0] = 137;break;			// first byte indicates FIN, Ping frame (10001001):
			case 'pong':	$frameHead[0] = 138;break;		// first byte indicates FIN, Pong frame (10001010):
		}
		if($payloadLength > 65535){			// set mask and payload length (using 1, 3 or 9 bytes)
			$payloadLengthBin=str_split(sprintf('%064b',$payloadLength),8);
			$frameHead[1]=($masked) ? 255 : 127;
			for($i=0;$i < 8;$i++)$frameHead[$i+2]=bindec($payloadLengthBin[$i]);
			if($frameHead[2] > 127)return array('error' => 'frame too large (1004)');		// most significant bit MUST be 0
    }elseif($payloadLength > 125){
			$payloadLengthBin=str_split(sprintf('%016b',$payloadLength),8);
			$frameHead[1]=($masked) ? 254 : 126;
        $frameHead[2]=bindec($payloadLengthBin[0]);
        $frameHead[3]=bindec($payloadLengthBin[1]);
    }else $frameHead[1]=($masked) ? $payloadLength+128 : $payloadLength;
		foreach(array_keys($frameHead) as $i)$frameHead[$i]=chr($frameHead[$i]);		// convert frame-head to string:;
		if($masked){
			$mask = array();			// generate a random mask:
			for($i=0;$i < 4;$i++)$mask[$i] = chr(rand(0, 255));
			$frameHead = array_merge($frameHead, $mask);
		}
		$frame=implode('', $frameHead);	
		for($i=0;$i < $payloadLength;$i++)$frame.=$masked  ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];	// append payload to frame:
		return $frame;
	}
}

function procFrame($frm,$con){
	global $CONNECTS_DATA;
	$idx=(string)$con;
	if(isset($frm['type'])){
		switch($frm['type']){
			case 'ping': return array('type'=>'pong','data'=> $frm['data']);
			case 'pong':
				if(isset($CONNECTS_DATA[$idx]['ping'])&&$CONNECTS_DATA[$idx]['ping'] > 0) $CONNECTS_DATA[$idx]['ping']--;
				return false;
			case 'close': 
				if(isset($CONNECTS_DATA[$idx]['closing'])&&$CONNECTS_DATA[$idx]['closing'])return false;
				return array('type'=>'close','data'=> $frm['data']);
		}
	}
	return onMessage($frm,$con);
}

//************** API functions ************************************

function onConnect($inf,$con){
	echo("Connected: $con\r\nConnection info:\r\n");
	print_r($inf);
	return;
}

function onMessage($msg,$con){
	echo($msg['data']."\r\n");
	return $msg;
}

function onClose($con){
	echo("Closed: $con\r\n");
	return;
}

function onError($error_message,$con){
	echo("Error: error_message\r\n");
	return;
}

function sendMessage($msg,$con){
	global $CONNECTS_DATA,$RECEIVERS;
	$idx=(string)$con;
	if(!isset($CONNECTS_DATA[$idx]))return 'error: connection broken.';
	$encmsg=composeOutput($msg,$CONNECTS_DATA[$idx]['Info']['Protocol']);
	if(is_array($encmsg))return $encmsg['error'];
	if($encmsg){
		$CONNECTS_DATA[$idx]['WriteBuffer'].=$encmsg;
		if(!in_array($con,$RECEIVERS))$RECEIVERS[]=$con;
	}
	return true;
}

function openClientConnection($target,$protocol='raw',$url='/',$host='127.0.0.1:8000',$orig='http://localhost'){
	global $CONNECTS_DATA,$RECEIVERS,$CONNECTS;
	$sock=stream_socket_client("tcp://$target",$errno,$errmsg);
	if(!$sock)return "Can't open socket. Error: $errmsg ($errno)";
	switch($protocol){
		case 'websocket':
			$CONNECTS_DATA[(string)$sock]=array('Connected'=>false,'Resource'=> $sock,'ReadBuffer'=>'','Info'=> array('Protocol'=>'websocket client'));
			$CONNECTS_DATA[(string)$sock]['WriteBuffer']="GET $url HTTP/1.1\r\n".
				"Host: $host\r\n".
				"Upgrade: websocket\r\n".
				"Connection: Upgrade\r\n".
				"Sec-WebSocket-Key: 2UpEuTeByLYZi2abJ1Dgxw==\r\n".
				"Origin: $orig\r\n".
				"Sec-WebSocket-Protocol: chat, superchat\r\n".
				"Sec-WebSocket-Version: 13\r\n\r\n";
			if(!in_array($sock,$RECEIVERS))$RECEIVERS[]=$sock;
			if(!in_array($sock,$CONNECTS))$CONNECTS[]=$sock;
			break;
		default: $CONNECTS_DATA[(string)$sock]=array('Connected'=>true,'Resource'=> $sock,'WriteBuffer'=>'','ReadBuffer'=>'','Info'=> array('Protocol'=>'raw'));
	}
	return $sock;
}
?>
