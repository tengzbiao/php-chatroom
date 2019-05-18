var userID;
var toUsreID;
var canSend = false;
var query = parseUrl(location.href);
var userName;
var userPortrait;

function parseUrl(url) {
    var result = {};
    var query = url.split("?");
    if (query.length === 1) {
        return;
    }
    query = query[1];
    var queryArr = query.split("&");
    queryArr.forEach(function (item) {
        var key = item.split("=")[0];
        var value = item.split("=")[1];
        result[key] = value;
    });
    userName = decodeURIComponent(result['name']);
    userPortrait = decodeURIComponent(result['portrait']);
}

function at(uid, nickname) {
    if (uid !== userID) {
        $('.text input').val('@' + nickname + ' ');
        toUsreID = uid;
        atChange();
    }
}

function atChange() {
    $('.text').bind('input propertychange', function () {
        var msg = $("#msg").val();
        if (!msg) {
            toUsreID = null;
        } else if (msg === '@') {
            $('.userlist a').click();
        }
    });
}


function process() {
    var host = "ws://127.0.0.1:8888";
    conn = new WebSocket(host);
    conn.onclose = function (evt) {
        canSend = false;
    };
    conn.onopen = function () {
        canSend = true;
        var message = {command: "join", body: {nickname: userName, portrait: userPortrait}};
        conn.send(JSON.stringify(message));
    };
    conn.onmessage = function (evt) {
        var messages = evt.data.split('\n');
        for (var i = 0; i < messages.length; i++) {
            var obj = JSON.parse(messages[i]);
            if (obj.command === 'connect') {
                userID = obj.body.user_id
            } else if (obj.command === 'join') {
                var info = '<li class="systeminfo"><span>【' + obj.body.nickname + '】加入了房间</span></li>';
                $("#chat_info").append(info);
            } else if (obj.command === 'online_users') {
                var list = obj.body.data;
                var info = '';
                $("#online_num").data('num', list.length).html('在线用户' + list.length + '人');
                for (var j = 0; j < list.length; j++) {
                    info += '<li onclick="at(\'' + list[j].user_id + '\',\'' + list[j].nickname + '\');"><img src="./assets/images/user/' + list[j].portrait + '.png"><b>' + list[j].nickname + '</b> </li>';
                }
                $("#online_users").html(info);
            } else if (obj.command === 'message') {
                var pos = obj.body.user_id == userID ? 'right' : 'left';
                var info = '<li onclick="at(\'' + obj.body.user_id + '\',\'' + obj.body.nickname + '\');" class="' + pos + '">' +
                    '<img src="./assets/images/user/' + obj.body.portrait + '.png">' +
                    '<b>' + obj.body.nickname + '</b>' +
                    '<i>' + obj.body.time + '</i>' +
                    '<div>' + obj.body.message + '</div>' +
                    '</li>';
                $("#chat_info").append(info);
            }
        }
    };
    $('.userlist a').click(function () {
        if (!canSend) return;
        var message = {command: "online_users", body: {}};
        conn.send(JSON.stringify(message));
    });
    atChange();
}

$(document).ready(function () {
// -------------------------登录页面---------------------------------------------------
    // 登录按钮
    $('#login').click(function (event) {
        userName = $('.login input').val(); // 用户昵称
        userPortrait = $('.login img').attr('portrait_id'); // 用户头像id
        if (userName == '') { // 如果不填昵称就给 "User" + ID
            alert('请输入昵称');
            return
        }
        window.location.href = './room.html?name=' + userName + '&portrait=' + userPortrait; // 页面跳转
    });


// --------------------聊天室内页面----------------------------------------------------
    if (userName && userPortrait) {
        process();
    }
    // 发送消息
    $('.text input').focus();
    $('#subxx').click(function (event) {
        if (!canSend) return;
        var str = $('.text input').val(); // 获取聊天内容
        str = str.replace(/\</g, '&lt;');
        str = str.replace(/\>/g, '&gt;');
        str = str.replace(/\n/g, '<br/>');
        if (str != '') {
            var message = {command: "message", body: {message: str}};
            if (toUsreID) {
                message.body.to_user = toUsreID;
            }
            conn.send(JSON.stringify(message));
            toUsreID = null;
            // 滚动条滚到最下面
            $('.scrollbar-macosx.scroll-content.scroll-scrolly_visible').animate({
                scrollTop: $('.scrollbar-macosx.scroll-content.scroll-scrolly_visible').prop('scrollHeight')
            }, 500);

        }
        $('.text input').val(''); // 清空输入框
        $('.text input').focus(); // 输入框获取焦点
    });


// -----下边的代码不用管---------------------------------------


    jQuery('.scrollbar-macosx').scrollbar();
    $('.topnavlist li a').click(function (event) {
        $('.topnavlist .popover').not($(this).next('.popover')).removeClass('show');
        $(this).next('.popover').toggleClass('show');
        if ($(this).next('.popover').attr('class') != 'popover fade bottom in') {
            $('.clapboard').removeClass('hidden');
        } else {
            $('.clapboard').click();
        }
    });
    $('.clapboard').click(function (event) {
        $('.topnavlist .popover').removeClass('show');
        $(this).addClass('hidden');
        $('.user_portrait img').attr('portrait_id', $('.user_portrait img').attr('ptimg'));
        $('.user_portrait img').attr('src', './assets/images/user/' + $('.user_portrait img').attr('ptimg') + '.png');
        $('.select_portrait img').removeClass('t');
        $('.select_portrait img').eq($('.user_portrait img').attr('ptimg') - 1).addClass('t');
        $('.rooms .user_name input').val('');
    });
    $('.select_portrait img').hover(function () {
        var portrait_id = $(this).attr('portrait_id');
        $('.user_portrait img').attr('src', './assets/images/user/' + portrait_id + '.png');
    }, function () {
        var t_id = $('.user_portrait img').attr('portrait_id');
        $('.user_portrait img').attr('src', './assets/images/user/' + t_id + '.png');
    });
    $('.select_portrait img').click(function (event) {
        var portrait_id = $(this).attr('portrait_id');
        $('.user_portrait img').attr('portrait_id', portrait_id);
        $('.select_portrait img').removeClass('t');
        $(this).addClass('t');
    });
    $('.face_btn,.faces').hover(function () {
        $('.faces').addClass('show');
    }, function () {
        $('.faces').removeClass('show');
    });
    $('.faces img').click(function (event) {
        if ($(this).attr('alt') != '') {
            $('.text input').val($('.text input').val() + '[em_' + $(this).attr('alt') + ']');
        }
        $('.faces').removeClass('show');
        $('.text input').focus();
    });
    $('.imgFileico').click(function (event) {
        $('.imgFileBtn').click();
    });

    $('.text input').keypress(function (e) {
        if (e.which == 13) {
            $('#subxx').click();
        }
    });
});