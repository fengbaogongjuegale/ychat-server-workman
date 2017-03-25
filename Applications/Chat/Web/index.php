<html><head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">

    <script type="text/javascript">
        var ws

        // 连接服务端
        function connect() {
            // 创建websocket
            ws = new WebSocket("ws://"+"192.168.1.100"+":7272");
            // 当socket连接打开时，输入用户名
            ws.onopen = onopen;
            // 当有消息时根据消息类型显示不同信息
            ws.onmessage = onmessage;
            ws.onclose = function() {
                console.log("连接关闭，定时重连");
                connect();
            };
            ws.onerror = function() {
                console.log("出现错误");
            };
        }

        // 连接建立时发送登录信息
        function onopen()
        {
            console.log("open");

        }

        // 服务端发来消息时
        function onmessage(e)
        {
            console.log(e.data);
        }



        // 发言
        function say(){

            ws.send("say");

        }

        function state () {
            console.log(ws.readyState );

        }


    </script>
</head>
<body >
<button onclick="connect()">connect</button>
<button onclick="say()">say </button>
<button onclick="state()">state</button>




</body>
</html>
