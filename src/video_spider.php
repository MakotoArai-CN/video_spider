<?php

/**
 * @package Video_spider
 * @author  iami233
 * @description 视频聚合解析类 原作者 iami233，由MakotoArai 二次修改，修复抖音400错误
 * @version 1.2.0
 * @link    https://github.com/5ime/Video_spider
 *
 **/

namespace Video_spider;

class Video
{
    public function pipixia($url)
    {
        $loc = get_headers($url, true)['Location'];
        preg_match('/item\/(.*)\?/', $loc, $id);
        $arr = json_decode($this->curl('https://is.snssdk.com/bds/cell/detail/?cell_type=1&aid=1319&app_name=super&cell_id=' . $id[1]), true);
        $video_url = $arr['data']['data']['item']['origin_video_download']['url_list'][0]['url'];
        if ($video_url) {
            $arr = [
                'code' => 200,
                'data' => [
                    'author' => $arr['data']['data']['item']['author']['name'],
                    'avatar' => $arr['data']['data']['item']['author']['avatar']['download_list'][0]['url'],
                    'time' => $arr['data']['data']['display_time'],
                    'title' => $arr['data']['data']['item']['content'],
                    'cover' => $arr['data']['data']['item']['cover']['url_list'][0]['url'],
                    'url' => $video_url
                ]
            ];
            return $arr;
        }
    }

    public function douyin($url)
    {
        // 尝试从 URL 中获取视频 ID
        $id = $this->extractId($url);

        // 检查 ID 是否有效
        if (empty($id)) {
            $finalUrl = $this->realy($url);
            // 如果finalUrl为false就返回错误信息
            if ($finalUrl === false) {
                return array('code' => 400, 'msg' => '无法解析视频 ID');
            }else{
                $id = $this->extractId($finalUrl);
            }
        }

        // 构造请求数据
        $header = array('User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1 Edg/122.0.0.0');

        // 发送请求获取视频信息
        $response = $this->curl('https://www.iesdouyin.com/share/video/' . $id, $header);
        $pattern = '/window\._ROUTER_DATA\s*=\s*(.*?)\<\/script>/s';
        preg_match($pattern, $response, $matches);

        if (empty($matches[1])) {
            return array('code' => 201, 'msg' => '解析失败');
        }

        $videoInfo = json_decode(trim($matches[1]), true);
        if (!isset($videoInfo['loaderData'])) {
            return array('code' => 201, 'msg' => '解析失败');
        }
        //替换 "playwm" 为 "play"
        $videoResUrl = str_replace('playwm', 'play', $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['video']['play_addr']['url_list'][0]);

        // 构造返回数据
        $arr = array(
            'code' => 200,
            'msg' => '解析成功',
            'data' => array(
                'author' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['author']['nickname'],
                'uid' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['author']['unique_id'],
                'avatar' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['author']['avatar_medium']['url_list'][0],
                'like' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['statistics']['digg_count'],
                'time' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]["create_time"],
                'title' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['desc'],
                'cover' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['video']['cover']['url_list'][0],
                'url' => $videoResUrl,
                'music' => array(
                    'author' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['music']['author'],
                    'avatar' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['music']['cover_large']['url_list'][0]
                )
            )
        );
        return $arr;
    }

    public function huoshan($url)
    {
        $loc = get_headers($url, true)['location'];
        preg_match('/item_id=(.*)&tag/', $loc, $id);
        $arr = json_decode($this->curl('https://share.huoshan.com/api/item/info?item_id=' . $id[1]), true);
        $url = $arr['data']['item_info']['url'];
        preg_match('/video_id=(.*)&line/', $url, $id);
        if ($id) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'cover' => $arr["data"]["item_info"]["cover"],
                    'url' => 'https://api-hl.huoshan.com/hotsoon/item/video/_playback/?video_id=' . $id[1]
                ]
            ];
            return $arr;
        }
    }

    public function weishi($url)
    {
        preg_match('/feed\/(.*)\b/', $url, $id);
        if (strpos($url, 'h5.weishi') != false) {
            $arr = json_decode($this->curl('https://h5.weishi.qq.com/webapp/json/weishi/WSH5GetPlayPage?feedid=' . $id[1]), true);
        } else {
            $arr = json_decode($this->curl('https://h5.weishi.qq.com/webapp/json/weishi/WSH5GetPlayPage?feedid=' . $url), true);
        }
        $video_url = $arr['data']['feeds'][0]['video_url'];
        if ($video_url) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'author' => $arr['data']['feeds'][0]['poster']['nick'],
                    'avatar' => $arr['data']['feeds'][0]['poster']['avatar'],
                    'time' => $arr['data']['feeds'][0]['poster']['createtime'],
                    'title' => $arr['data']['feeds'][0]['feed_desc_withat'],
                    'cover' => $arr['data']['feeds'][0]['images'][0]['url'],
                    'url' => $video_url
                ]
            ];
            return $arr;
        }
    }

    public function weibo($url)
    {
        if (strpos($url, 'show?fid=') != false) {
            preg_match('/fid=(.*)/', $url, $id);
            $arr = json_decode($this->weibo_curl($id[1]), true);
        } else {
            preg_match('/\d+\:\d+/', $url, $id);
            $arr = json_decode($this->weibo_curl($id[0]), true);
        }
        if ($arr) {
            $one = key($arr['data']['Component_Play_Playinfo']['urls']);
            $video_url = $arr['data']['Component_Play_Playinfo']['urls'][$one];
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'author' => $arr['data']['Component_Play_Playinfo']['author'],
                    'avatar' => $arr['data']['Component_Play_Playinfo']['avatar'],
                    'time' => $arr['data']['Component_Play_Playinfo']['real_date'],
                    'title' => $arr['data']['Component_Play_Playinfo']['title'],
                    'cover' => $arr['data']['Component_Play_Playinfo']['cover_image'],
                    'url' => $video_url
                ]
            ];
            return $arr;
        }
    }

    public function lvzhou($url)
    {
        $text = $this->curl($url);
        preg_match('/<div class=\"status-title\">(.*)<\/div>/', $text, $video_title);
        preg_match('/<div style=\"background-image:url\((.*)\)/', $text, $video_cover);
        preg_match('/<video src=\"([^\"]*)\"/', $text, $video_url);
        preg_match('/<div class=\"nickname\">(.*)<\/div>/', $text, $video_author);
        preg_match('/<a class=\"avatar\"><img src=\"(.*)\?/', $text, $video_author_img);
        preg_match('/已获得(.*)条点赞<\/div>/', $text, $video_like);
        if ($video_url[1]) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'author' => $video_author[1],
                    'avatar' => str_replace('1080.180', '1080.680', $video_author_img)[1],
                    'like' => $video_like[1],
                    'title' => $video_title[1],
                    'cover' => $video_cover[1],
                    'url' => str_replace('amp;', '', $video_url[1]),
                ]
            ];
            return $arr;
        }
    }

    public function zuiyou($url)
    {
        $text = $this->curl($url);
        preg_match('/fullscreen=\"false\" src=\"(.*?)\"/', $text, $video);
        preg_match('/:<\/span><h1>(.*?)<\/h1><\/div><div class=/', $text, $video_title);
        preg_match('/poster=\"(.*?)\">/', $text, $video_cover);
        $video_url = str_replace('\\', '/', str_replace('u002F', '', $video[1]));
        preg_match('/<span class=\"SharePostCard__name\">(.*?)<\/span>/', $text, $video_author);
        if ($video_url) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'author' => $video_author[1],
                    'title' => $video_title[1],
                    'cover' => $video_cover[1],
                    'url' => $video_url,
                ]
            ];
            return $arr;
        }
    }

    public function bbq($url)
    {
        preg_match('/id=(.*)\b/', $url, $id);
        $arr = json_decode($this->curl('https://bbq.bilibili.com/bbq/app-bbq/sv/detail?svid=' . $id[1]), true);
        $video_url = $arr['data']['play']['file_info'][0]['url'];
        if ($video_url) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'author' => $arr['data']['user_info']['uname'],
                    'avatar' => $arr['data']['user_info']['face'],
                    'time' => $arr['data']['pubtime'],
                    'like' => $arr['data']['like'],
                    'title' => $arr['data']['title'],
                    'cover' => $arr['data']['cover_url'],
                    'url' => $video_url,
                ]
            ];
            return $arr;
        }
    }

    public function kuaishou($url)
    {
        if (strpos($url, 'v.kuaishou.com') != false) {
            $url = get_headers($url, true)['Location'];
            preg_match('/photoId=(.*?)\&/', $url, $matches);
        } else {
            preg_match('/short-video\/(.*?)\?/', $url, $matches);
        }
        $headers = array(
            'Cookie: did=web_9bceee20fa5d4a968535a27e538bf51b; didv=1655992503000;',
            'Referer: ' . $url,
            'Content-Type: application/json'
        );
        $post_data = '{"photoId": "' . str_replace(['video/', '?'], '', $matches[1]) . '","isLongVideo": false}';
        $url = 'https://v.m.chenzhongtech.com/rest/wd/photo/info';
        $json = json_decode($this->curl($url, $headers, $post_data), true);
        $video_url = $json['photo']['mainMvUrls'][key($json['photo']['mainMvUrls'])]['url'];
        if ($video_url) {
            $arr = array(
                'code' => 200,
                'msg' => '解析成功',
                'data' => array(
                    'avatar' => $json['photo']['headUrl'],
                    'author' => $json['photo']['userName'],
                    'time' => $json['photo']['timestamp'],
                    'title' => $json['photo']['caption'],
                    'cover' => $json['photo']['coverUrls'][key($json['photo']['coverUrls'])]['url'],
                    'url' => $json['photo']['mainMvUrls'][key($json['photo']['mainMvUrls'])]['url'],
                )
            );
            return $arr;
        }
    }

    public function quanmin($id)
    {
        if (strpos($id, 'quanmin.baidu.com/v/')) {
            preg_match('/v\/(.*?)\?/', $id, $vid);
            $id = $vid[1];
        }
        $arr = json_decode($this->curl('https://quanmin.hao222.com/wise/growth/api/sv/immerse?source=share-h5&pd=qm_share_mvideo&vid=' . $id . '&_format=json'), true);
        if ($arr) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'author' => $arr["data"]["author"]['name'],
                    'avatar' => $arr["data"]["author"]["icon"],
                    'title' => $arr["data"]["meta"]["title"],
                    'cover' => $arr["data"]["meta"]["image"],
                    'url' => $arr["data"]["meta"]["video_info"]["clarityUrl"][0]['url']
                ]
            ];
            return $arr;
        }
    }

    public function basai($id)
    {
        $arr = json_decode($this->curl('http://www.moviebase.cn/uread/api/m/video/' . $id . '?actionkey=300303'), true);
        $video_url = $arr[0]['data']['videoUrl'];
        if ($video_url) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'time' => $arr[0]['data']['createDate'],
                    'title' => $arr[0]['data']['title'],
                    'cover' => $arr[0]['data']['coverUrl'],
                    'url' => $video_url
                ]
            ];
            return $arr;
        }
    }

    public function before($url)
    {
        preg_match('/detail\/(.*)\?/', $url, $id);
        $arr = json_decode($this->curl('https://hlg.xiatou.com/h5/feed/detail?id=' . $id[1]), true);
        $video_url = $arr['data'][0]['mediaInfoList'][0]['videoInfo']['url'];
        if ($video_url) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'author' => $arr['data'][0]['author']['nickName'],
                    'avatar' => $arr['data'][0]['author']['avatar']['url'],
                    'like' => $arr['data'][0]['diggCount'],
                    'time' => $arr['recTimeStamp'],
                    'title' => $arr['data'][0]['title'],
                    'cover' => $arr['data'][0]['staticCover'][0]['url'],
                    'url' => $video_url
                ]
            ];
            return $arr;
        }
    }

    public function kaiyan($url)
    {
        preg_match('/\?vid=(.*)\b/', $url, $id);
        $arr = json_decode($this->curl('https://baobab.kaiyanapp.com/api/v1/video/' . $id[1] . '?f=web'), true);
        $video = 'https://baobab.kaiyanapp.com/api/v1/playUrl?vid=' . $id[1] . '&resourceType=video&editionType=default&source=aliyun&playUrlType=url_oss&ptl=true';
        $video_url = get_headers($video, true)["Location"];
        if ($video_url) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'title' => $arr['title'],
                    'cover' => $arr['coverForFeed'],
                    'url' => $video_url
                ]
            ];
            return $arr;
        }
    }

    public function momo($url)
    {
        preg_match('/new-share-v2\/(.*)\.html/', $url, $id);
        if (count($id) < 1) {
            preg_match('/momentids=(\w+)/', $url, $id);
        }
        $post_data = ["feedids" => $id[1],];
        $arr = json_decode($this->post_curl('https://m.immomo.com/inc/microvideo/share/profiles', $post_data), true);
        $video_url = $arr['data']['list'][0]['video']['video_url'];
        if ($video_url) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'author' => $arr['data']['list'][0]['user']['name'],
                    'avatar' => $arr['data']['list'][0]['user']['img'],
                    'uid' => $arr['data']['list'][0]['user']['momoid'],
                    'sex' => $arr['data']['list'][0]['user']['sex'],
                    'age' => $arr['data']['list'][0]['user']['age'],
                    'city' => $arr['data']['list'][0]['video']['city'],
                    'like' => $arr['data']['list'][0]['video']['like_cnt'],
                    'title' => $arr['data']['list'][0]['content'],
                    'cover' => $arr['data']['list'][0]['video']['cover']['l'],
                    'url' => $video_url
                ]
            ];
            return $arr;
        }
    }

    public function vuevlog($url)
    {
        $text = $this->curl($url);
        preg_match('/<title>(.*?)<\/title>/', $text, $video_title);
        preg_match('/<meta name=\"twitter:image\" content=\"(.*?)\">/', $text, $video_cover);
        preg_match('/<meta property=\"og:video:url\" content=\"(.*?)\">/', $text, $video_url);
        preg_match('/<div class=\"infoItem name\">(.*?)<\/div>/', $text, $video_author);
        preg_match('/<div class="avatarContainer"><img src="(.*?)\"/', $text, $video_avatar);
        preg_match('/<div class=\"likeTitle\">(.*) friends/', $text, $video_like);
        $video_url = $video_url[1];
        if ($video_url) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'author' => $video_author[1],
                    'avatar' => $video_avatar[1],
                    'like' => $video_like[1],
                    'title' => $video_title[1],
                    'cover' => $video_cover[1],
                    'url' => $video_url,
                ]
            ];
            return $arr;
        }
    }

    public function xiaokaxiu($url)
    {
        preg_match('/id=(.*)\b/', $url, $id);
        $sign = md5('S14OnTD#Qvdv3L=3vm&time=' . time());
        $arr = json_decode($this->curl('https://appapi.xiaokaxiu.com/api/v1/web/share/video/' . $id[1] . '?time=' . time(), ["x-sign : $sign"]), true);
        if ($arr['code'] != -2002) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'author' => $arr['data']['video']['user']['nickname'],
                    'avatar' => $arr['data']['video']['user']['avatar'],
                    'like' => $arr['data']['video']['likedCount'],
                    'time' => $arr['data']['video']['createdAt'],
                    'title' => $arr['data']['video']['title'],
                    'cover' => $arr['data']['video']['cover'],
                    'url' => $arr['data']['video']['url'][0]
                ]
            ];
            return $arr;
        }
    }

    public function pipigaoxiao($url)
    {
        preg_match('/post\/(.*)/', $url, $id);
        $arr = json_decode($this->pipigaoxiao_curl($id[1]), true);
        $id = $arr["data"]["post"]["imgs"][0]["id"];
        if ($id) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'title' => $arr["data"]["post"]["content"],
                    'cover' => 'https://file.ippzone.com/img/view/id/' . $arr["data"]["post"]["imgs"][0]["id"],
                    'url' => $arr["data"]["post"]["videos"]["$id"]["url"]
                ]
            ];
            return $arr;
        }
    }

    public function quanminkge($url)
    {
        preg_match('/\?s=(.*)/', $url, $id);
        $text = $this->curl('https://kg.qq.com/node/play?s=' . $id[1]);
        preg_match('/<title>(.*?)-(.*?)-/', $text, $video_title);
        preg_match('/cover\":\"(.*?)\"/', $text, $video_cover);
        preg_match('/playurl_video\":\"(.*?)\"/', $text, $video_url);
        preg_match('/{\"activity_id\":0\,\"avatar\":\"(.*?)\"/', $text, $video_avatar);
        preg_match('/<p class=\"singer_more__time\">(.*?)<\/p>/', $text, $video_time);
        if ($video_url[1]) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'title' => $video_title[2],
                    'cover' => $video_cover[1],
                    'url' => $video_url[1],
                    'author' => $video_title[1],
                    'avatar' => $video_avatar[1],
                    'time' => $video_time[1],
                ]
            ];
            return $arr;
        }
    }

    public function xigua($url)
    {
        if (strpos($url, 'v.ixigua.com') != false) {
            $loc = get_headers($url, true)['Location'];
            preg_match('/video\/(.*)\//', $loc, $id);
            $url = 'https://www.ixigua.com/' . $id[1];
        }
        $headers = ["User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36 ", "cookie:MONITOR_WEB_ID=7892c49b-296e-4499-8704-e47c1b150c18; ixigua-a-s=1; ttcid=af99669b6304453480454f150701d5c226; BD_REF=1; __ac_nonce=060d88ff000a75e8d17eb; __ac_signature=_02B4Z6wo00f01kX9ZpgAAIDAKIBBQUIPYT5F2WIAAPG2ad; ttwid=1%7CcIsVF_3vqSIk4XErhPB0H2VaTxT0tdsTMRbMjrJOPN8%7C1624806049%7C08ce7dd6f7d20506a41ba0a331ef96a6505d96731e6ad9f6c8c709f53f227ab1"];
        $text = $this->curl($url, $headers);
        preg_match('/<script id=\"SSR_HYDRATED_DATA\">window._SSR_HYDRATED_DATA=(.*?)<\/script>/', $text, $jsondata);
        $data = json_decode(str_replace('undefined', 'null', $jsondata[1]), 1);
        $result = $data["anyVideo"]["gidInformation"]["packerData"]["video"];
        $video_url = base64_decode($result["videoResource"]["dash"]["dynamic_video"]["dynamic_video_list"][2]["main_url"]);
        $music_url = base64_decode($result["videoResource"]["dash"]["dynamic_video"]["dynamic_audio_list"][0]["main_url"]);
        $video_author = $result['user_info']['name'];
        $video_avatar = str_replace('300x300.image', '300x300.jpg', $result['user_info']['avatar_url']);
        $video_cover = $data["anyVideo"]["gidInformation"]["packerData"]["video"]["poster_url"];
        $video_title = $result["title"];
        if ($video_url) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'author' => $video_author,
                    'avatar' => $video_avatar,
                    'like' => $result['video_like_count'],
                    'time' => $result['video_publish_time'],
                    'title' => $video_title,
                    'cover' => $video_cover,
                    'url' => $video_url,
                    'music' => [
                        'url' => $music_url
                    ]
                ]
            ];
            return $arr;
        }
    }

    public function doupai($url)
    {
        preg_match("/topic\/(.*?).html/", $url, $d_url);
        $vid = $d_url[1];
        $base_url = "https://v2.doupai.cc/topic/" . $vid . ".json";
        $data = json_decode($this->curl($base_url), true);
        $url = $data["data"]["videoUrl"];
        $title = $data["data"]["name"];
        $cover = $data["data"]["imageUrl"];
        $time = $data['data']['createdAt'];
        $author = $data['data']['userId'];
        if ($url) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'title' => $title,
                    'cover' => $cover,
                    'time' => $time,
                    'author' => $author['name'],
                    'avatar' => $author['avatar'],
                    'url' => $url
                ]
            ];
            return $arr;
        }
    }

    public function sixroom($url)
    {
        preg_match("/http[s]?:\/\/(?:[a-zA-Z]|[0-9]|[$-_@.&+]|[!*\(\),]|(?:%[0-9a-fA-F][0-9a-fA-F]))+/", $url, $deal_url);
        $headers = ['user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.125 Safari/537.36', 'x-requested-with' => 'XMLHttpRequest'];
        $rows = $this->curl($deal_url[0], $headers);
        preg_match('/tid: \'(\w+)\',/', $rows, $tid);
        $base_url = 'https://v.6.cn/message/message_home_get_one.php';
        $content = $this->curl($base_url . '?tid=' . $tid[1], $headers);
        $content = json_decode($content, 1);
        if ($content) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'title' => $content["content"]["content"][0]["content"]['title'],
                    'cover' => $content["content"]["content"][0]["content"]['url'],
                    'url' => $content["content"]["content"][0]["content"]['playurl'],
                    'author' => $content["content"]["content"][0]['alias'],
                    'avatar' => $content["content"]["content"][0]['userpic'],
                ]
            ];
            return $arr;
        }
    }

    public function huya($url)
    {
        preg_match('/\/(\d+).html/', $url, $vid);
        $api = 'https://liveapi.huya.com/moment/getMomentContent';
        $response = $this->curl($api . '?videoId=' . $vid[1], ['user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.102 Safari/537.36', 'referer' => 'https://v.huya.com/',]);
        $content = json_decode($response, 1);
        if ($content['status'] === 200) {
            $url = $content["data"]["moment"]["videoInfo"]["definitions"][0]["url"];
            $cover = $content["data"]["moment"]["videoInfo"]["videoCover"];
            $title = $content["data"]["moment"]["videoInfo"]["videoTitle"];
            $avatarUrl = $content["data"]["moment"]["videoInfo"]["avatarUrl"];
            $author = $content["data"]["moment"]["videoInfo"]["nickName"];
            $time = $content["data"]["moment"]["cTime"];
            $like = $content["data"]["moment"]["favorCount"];
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'title' => $title,
                    'cover' => $cover,
                    'url' => $url,
                    'time' => $time,
                    'like' => $like,
                    'author' => $author,
                    'avatar' => $avatarUrl
                ]
            ];
            return $arr;
        }
    }

    public function pear($url)
    {
        $html = $this->curl($url);
        preg_match('/<h1 class=\"video-tt\">(.*?)<\/h1>/', $html, $title);
        preg_match('/_(\d+)/', $url, $feed_id);
        $base_url = sprintf("https://www.pearvideo.com/videoStatus.jsp?contId=%s&mrd=%s", $feed_id[1], time());
        $response = $this->pear_curl($base_url, $url);
        $content = json_decode($response, 1);
        if ($content['resultCode'] == 1) {
            $video = $content["videoInfo"]["videos"]["srcUrl"];
            $cover = $content["videoInfo"]["video_image"];
            $timer = $content["systemTime"];
            $video_url = str_replace($timer, "cont-" . $feed_id[1], $video);
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'title' => $title[1],
                    'cover' => $cover,
                    'url' => $video_url,
                    'time' => $timer,
                ]
            ];
            return $arr;
        }
    }

    public function xinpianchang($url)
    {
        $api_headers = ["User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.125 Safari/537.36", "referer" => $url, "origin" => "https://www.xinpianchang.com", "content-type" => "application/json"];
        $home_headers = ["User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.125 Safari/537.36", "upgrade-insecure-requests" => "1"];
        $html = $this->curl($url, $home_headers);
        preg_match('/var modeServerAppKey = "(.*?)";/', $html, $key);
        preg_match('/var vid = "(.*?)";/', $html, $vid);
        $base_url = sprintf("https://mod-api.xinpianchang.com/mod/api/v2/media/%s?appKey=%s&extend=%s", $vid[1], $key[1], "userInfo,userStatus");
        $response = $this->xinpianchang_curl($base_url, $api_headers, $url);
        $content = json_decode($response, 1);
        if ($content['status'] == 0) {
            $cover = $content['data']["cover"];
            $title = $content['data']["title"];
            $videos = $content['data']["resource"]["progressive"];
            $author = $content['data']['owner']['username'];
            $avatar = $content['data']['owner']['avatar'];
            $video = [];
            foreach ($videos as $v) {
                $video[] = ['profile' => $v['profile'], 'url' => $v['url']];
            }
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'author' => $author,
                    'avatar' => $avatar,
                    'cover' => $cover,
                    'title' => $title,
                    'url' => $video
                ]
            ];
            return $arr;
        }
    }

    public function acfan($url)
    {
        $headers = ['User-Agent:Mozilla/5.0 (iPhone; CPU iPhone OS 11_0 like Mac OS X) AppleWebKit/604.1.38 (KHTML, like Gecko) Version/11.0 Mobile/15A372 Safari/604.1'];
        $html = $this->acfun_curl($url, $headers);
        preg_match('/var videoInfo =\s(.*?);/', $html, $info);
        $videoInfo = json_decode(trim($info[1]), 1);
        preg_match('/var playInfo =\s(.*?);/', $html, $play);
        $playInfo = json_decode(trim($play[1]), 1);
        if ($html) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    'title' => $videoInfo['title'],
                    'cover' => $videoInfo['cover'],
                    'url' => $playInfo['streams'][0]['playUrls'][0],
                ]
            ];
            return $arr;
        }
    }

    public function meipai($url)
    {
        $headers = ["User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36 ",];
        $html = $this->curl($url, $headers);
        preg_match('/data-video="(.*?)"/', $html, $content);
        preg_match('/<meta name=\"description\" content="(.*?)"/', $html, $title);
        $video_bs64 = $content[1];
        $hex = $this->getHex($video_bs64);
        $dec = $this->getDec($hex['hex_1']);
        $d = $this->sub_str($hex['str_1'], $dec['pre']);
        $p = $this->getPos($d, $dec['tail']);
        $kk = $this->sub_str($d, $p);
        $video = 'https:' . base64_decode($kk);
        if ($video_bs64) {
            $arr = [
                'code' => 200,
                'msg' => '解析成功',
                'data' => [
                    "title" => $title[1],
                    "url" => $video
                ]
            ];
            return $arr;
        }
    }

    private function acfun_curl($url, $headers = [])
    {
        $header = ['User-Agent:Mozilla/5.0 (iPhone; CPU iPhone OS 11_0 like Mac OS X) AppleWebKit/604.1.38 (KHTML, like Gecko) Version/11.0 Mobile/15A372 Safari/604.1'];
        $con = curl_init((string)$url);
        curl_setopt($con, CURLOPT_HEADER, false);
        curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
        if (!empty($headers)) {
            curl_setopt($con, CURLOPT_HTTPHEADER, $headers);
        } else {
            curl_setopt($con, CURLOPT_HTTPHEADER, $header);
        }
        curl_setopt($con, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($con, CURLOPT_TIMEOUT, 5000);
        return curl_exec($con);
    }

    private function curl($url, $header = null, $data = null)
    {
        $con = curl_init((string)$url);
        curl_setopt($con, CURLOPT_HEADER, false);
        curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($con, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($con, CURLOPT_AUTOREFERER, 1);
        if (isset($header)) {
            curl_setopt($con, CURLOPT_HTTPHEADER, $header);
        }
        if (isset($data)) {
            curl_setopt($con, CURLOPT_POST, true);
            curl_setopt($con, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($con, CURLOPT_TIMEOUT, 5000);
        $result = curl_exec($con);
        return $result;
    }

    private function realy($url)
    {
        // 初始化cURL会话
        $ch = curl_init();

        // 设置cURL选项
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 返回结果而不是直接输出
        curl_setopt($ch, CURLOPT_HEADER, 1); // 包括header信息在输出中
        curl_setopt($ch, CURLOPT_NOBODY, 0); // 不包括body内容，但这里我们想要body所以设置为0
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // 跟随重定向
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 取消SSL验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 检查证书中的主机名是否与请求的目标一致，false表示不检查

        // 执行cURL会话，并获取响应
        $response = curl_exec($ch);

        if ($response === false) {
            curl_close($ch);
            return $response;
        } else {
            // 获取响应信息
            $info = curl_getinfo($ch);
            curl_close($ch);
            // 返回最终跳转的URL
            return $info['url'];
        }
    }


    private function post_curl($url, $post_data)
    {
        $postdata = http_build_query($post_data);
        $options = ['http' => ['method' => 'POST', 'content' => $postdata,]];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        return $result;
    }

    private function pipigaoxiao_curl($id)
    {
        $post_data = "{\"pid\":" . $id . ",\"type\":\"post\",\"mid\":null}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://share.ippzone.com/ppapi/share/fetch_content");
        curl_setopt($ch, CURLOPT_REFERER, "http://share.ippzone.com/ppapi/share/fetch_content");
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    private function weibo_curl($id)
    {
        $cookie = "login_sid_t=6b652c77c1a4bc50cb9d06b24923210d; cross_origin_proto=SSL; WBStorage=2ceabba76d81138d|undefined; _s_tentry=passport.weibo.com; Apache=7330066378690.048.1625663522444; SINAGLOBAL=7330066378690.048.1625663522444; ULV=1625663522450:1:1:1:7330066378690.048.1625663522444:; TC-V-WEIBO-G0=35846f552801987f8c1e8f7cec0e2230; SUB=_2AkMXuScYf8NxqwJRmf8RzmnhaoxwzwDEieKh5dbDJRMxHRl-yT9jqhALtRB6PDkJ9w8OaqJAbsgjdEWtIcilcZxHG7rw; SUBP=0033WrSXqPxfM72-Ws9jqgMF55529P9D9W5Qx3Mf.RCfFAKC3smW0px0; XSRF-TOKEN=JQSK02Ijtm4Fri-YIRu0-vNj";
        $post_data = "data={\"Component_Play_Playinfo\":{\"oid\":\"$id\"}}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://weibo.com/tv/api/component?page=/tv/show/" . $id);
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_REFERER, "https://weibo.com/tv/show/" . $id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    private function pear_curl($url, $referer)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    private function xinpianchang_curl($url, $headers, $referer)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    protected function getHex($url)
    {
        $length = strlen($url);
        $hex_1 = substr($url, 0, 4);
        $str_1 = substr($url, 4, $length);
        return ['hex_1' => strrev($hex_1), 'str_1' => $str_1];
    }

    protected function getDec($hex)
    {
        $b = hexdec($hex);
        $length = strlen($b);
        $c = str_split(substr($b, 0, 2));
        $d = str_split(substr($b, 2, $length));
        return ['pre' => $c, 'tail' => $d,];
    }

    protected function sub_str($a, $b)
    {
        $length = strlen($a);
        $k = $b[0];
        $c = substr($a, 0, $k);
        $d = substr($a, $k, $b[1]);
        $temp = str_replace($d, '', substr($a, $k, $length));
        return $c . $temp;
    }

    protected function getPos($a, $b)
    {
        $b[0] = strlen($a) - (int)$b[0] - (int)$b[1];
        return $b;
    }

    // 辅助函数，用于从 URL 中提取视频 ID
    private function extractId($url)
    {
        $loc = get_headers($url, true)['Location'] ?? $url;
        preg_match('/[0-9]+/', $loc, $id);
        return !empty($id) ? $id[0] : null;
    }
}
