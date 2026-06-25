(function(){'use strict';

var C=window.__itp;if(!C||!C.ajaxUrl)return;
var _buffer=[],_maxBatch=10,_flushMs=5000,_scrollFired={},_startTime=Date.now(),_pageCount,_timeFired=false;

/* ── Cookie helpers ── */
function gC(n){var m=document.cookie.match('(^|; )'+n+'=([^;]*)');return m?decodeURIComponent(m[2]):null;}
function sC(n,v,d){var e='';if(d){var dt=new Date();dt.setTime(dt.getTime()+d*864e5);e=';expires='+dt.toUTCString();}document.cookie=n+'='+encodeURIComponent(v)+e+';path=/;SameSite=Lax';}
function sid11(){var a='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-',s='';try{var b=new Uint8Array(11);crypto.getRandomValues(b);for(var i=0;i<11;i++)s+=a[b[i]&63];}catch(e){for(var j=0;j<11;j++)s+=a[Math.random()*64|0];}return s;}

var days=C.cookieDays||365;

/* ── Visitor ID ── */
var vid=gC('_itp_vid');if(!vid){vid=sid11();sC('_itp_vid',vid,days);}

/* ── Session ── */
var sid=null;try{sid=sessionStorage.getItem('_itp_sid');}catch(e){}
var isNewSession=!sid;
if(!sid){sid=sid11();try{sessionStorage.setItem('_itp_sid',sid);}catch(e){}}

/* ── Visit number ── */
var vn=parseInt(gC('_itp_vn'),10)||0;
if(isNewSession){vn++;sC('_itp_vn',String(vn),days);}

/* ── Page count ── */
_pageCount=1;
try{var pc=sessionStorage.getItem('_itp_pc');_pageCount=pc?parseInt(pc,10)+1:1;sessionStorage.setItem('_itp_pc',String(_pageCount));}catch(e){}

/* ── First touch ── */
var ft=gC('_itp_ft');var ftData=null;
if(!ft){ftData={ref:document.referrer||'',utms:getUtms(),params:getAllParams(),ts:new Date().toISOString(),lp:location.href};sC('_itp_ft',JSON.stringify(ftData),days);}else{try{ftData=JSON.parse(ft);}catch(e){ftData=null;}}

/* ── Device info ── */
var sw=screen.width,ua=navigator.userAgent;
var deviceType=sw<768?'mobile':sw<1024?'tablet':'desktop';
var browserName=detectBrowser(ua),osName=detectOS(ua);

function detectBrowser(u){if(u.indexOf('Firefox/')>-1)return'Firefox';if(u.indexOf('Edg/')>-1)return'Edge';if(u.indexOf('OPR/')>-1||u.indexOf('Opera/')>-1)return'Opera';if(u.indexOf('Chrome/')>-1)return'Chrome';if(u.indexOf('Safari/')>-1)return'Safari';return'Other';}
function detectOS(u){if(u.indexOf('Windows')>-1)return'Windows';if(u.indexOf('Mac OS')>-1)return'macOS';if(u.indexOf('Linux')>-1)return'Linux';if(u.indexOf('Android')>-1)return'Android';if(u.indexOf('iPhone')>-1||u.indexOf('iPad')>-1)return'iOS';return'Other';}

/* ── URL / UTM ── */
function getUtms(){var p=new URLSearchParams(location.search);return{utm_source:p.get('utm_source')||'',utm_medium:p.get('utm_medium')||'',utm_campaign:p.get('utm_campaign')||'',utm_content:p.get('utm_content')||'',utm_term:p.get('utm_term')||''};}
function getAllParams(){var o={},p=new URLSearchParams(location.search);p.forEach(function(v,k){o[k]=v;});return o;}
function getClickIds(){var ids=['fbclid','gclid','msclkid','dclid','li_fat_id','ttclid','twclid','sccid','igshid','mc_cid'],o={},p=new URLSearchParams(location.search);ids.forEach(function(k){var v=p.get(k);if(v)o[k]=v;});return o;}

var utms=getUtms(),clickIds=getClickIds(),allParams=getAllParams();

/* ── Referrer type ── */
function getReferrerType(){
    var ref=document.referrer;if(!ref)return'direct';
    try{var h=new URL(ref).hostname;}catch(e){return'direct';}
    if(h===location.hostname)return'internal';
    // Paid
    if(Object.keys(clickIds).length)return'paid';
    // UTM email
    if(utms.utm_medium&&utms.utm_medium.toLowerCase()==='email')return'email';
    // Organic
    var org=['google','bing','yahoo','duckduckgo','baidu','yandex'];
    for(var i=0;i<org.length;i++){if(h.indexOf(org[i])>-1)return'organic';}
    // Social
    var soc=['facebook','twitter','linkedin','instagram','reddit','tiktok','youtube','pinterest','t.co'];
    for(var j=0;j<soc.length;j++){if(h.indexOf(soc[j])>-1)return'social';}
    return'referral';
}
var refType=getReferrerType(),refDomain='';
try{if(document.referrer)refDomain=new URL(document.referrer).hostname;}catch(e){}

/* ── Performance ── */
function getPerf(){try{var n=performance.getEntriesByType('navigation')[0];if(!n)return null;return{dns:Math.round(n.domainLookupEnd-n.domainLookupStart),tcp:Math.round(n.connectEnd-n.connectStart),ttfb:Math.round(n.responseStart-n.requestStart),dom_ready:Math.round(n.domContentLoadedEventEnd-n.startTime),load:Math.round(n.loadEventEnd-n.startTime)};}catch(e){return null;}}

/* ── Days since helpers ── */
var daysSinceFirst=0,daysSinceLast=0;
if(ftData&&ftData.ts){daysSinceFirst=Math.floor((Date.now()-new Date(ftData.ts).getTime())/864e5);}
var lastVisit=gC('_itp_lv');
if(lastVisit){daysSinceLast=Math.floor((Date.now()-parseInt(lastVisit,10))/864e5);}
sC('_itp_lv',String(Date.now()),days);

/* ── Event builder ── */
function buildEvent(type,data){
    data=data||{};
    var ctx=C.ctx||{};
    var evt={
        // key is added server-side by proxy, not exposed here
        visitor_id:vid,session_id:sid,site_id:ctx.site_id||'',
        event_type:type,event_ts:new Date().toISOString(),
        event_value:data.event_value||'',event_label:data.event_label||'',event_category:data.event_category||'',event_data:data.event_data||'',
        page_url:location.href,page_path:location.pathname,page_title:document.title
    };
    // WP context
    var cKeys=['site_name','wp_context','post_id','post_type','post_slug','post_author','post_date','post_word_count','categories','tags','custom_taxonomies','archive_type','archive_term','archive_taxonomy','search_query','search_results','page_template','wp_user_id','wp_user_role','is_logged_in','woo_product'];
    for(var i=0;i<cKeys.length;i++){if(ctx[cKeys[i]]!==undefined)evt[cKeys[i]]=ctx[cKeys[i]];}
    // Device
    evt.device_type=deviceType;evt.browser_name=browserName;evt.os_name=osName;
    evt.screen_resolution=screen.width+'x'+screen.height;evt.user_language=navigator.language||'';evt.timezone=typeof Intl!=='undefined'?Intl.DateTimeFormat().resolvedOptions().timeZone:'';
    // Source
    evt.referrer_url=document.referrer||'';evt.referrer_domain=refDomain;evt.referrer_type=refType;
    evt.utm_source=utms.utm_source;evt.utm_medium=utms.utm_medium;evt.utm_campaign=utms.utm_campaign;evt.utm_content=utms.utm_content;evt.utm_term=utms.utm_term;
    evt.click_ids=JSON.stringify(clickIds);evt.url_params=JSON.stringify(allParams);
    // Session
    evt.visit_number=vn;evt.session_page_count=_pageCount;evt.is_first_visit=vn===1;evt.is_bounce=_pageCount===1;
    evt.days_since_first_visit=daysSinceFirst;evt.days_since_last_visit=daysSinceLast;
    // First touch
    evt.first_touch=ftData?JSON.stringify(ftData):'';
    // Perf
    evt.perf_data=JSON.stringify(getPerf());
    return evt;
}

/* ── Buffer & dispatch ── */
function push(type,data){_buffer.push(buildEvent(type,data));if(_buffer.length>=_maxBatch)_flush();}
function _flush(){if(!_buffer.length)return;var batch=_buffer.splice(0,_maxBatch);var url=C.ajaxUrl+'?action=itp_collect&nonce='+(C.nonce||'');try{var json=JSON.stringify(batch);navigator.sendBeacon(url,new Blob([json],{type:'application/json'}));}catch(e){try{fetch(url,{method:'POST',body:JSON.stringify(batch),keepalive:true,headers:{'Content-Type':'application/json'}});}catch(e2){/* silent */}}}

setInterval(_flush,_flushMs);
document.addEventListener('visibilitychange',function(){if(document.visibilityState==='hidden')_flush();});
window.addEventListener('beforeunload',_flush);

/* ── Public API ── */
C.track=function(type,data){push(type,data||{});};

/* ── Auto-track ── */
var S=C.settings||{};

// Page view
if(S.pv){push('pv');}

// Scroll depth
if(S.scroll){
    var thresholds=[25,50,75,100];
    var _scrollTick=false;
    window.addEventListener('scroll',function(){
        if(_scrollTick)return;
        _scrollTick=true;
        requestAnimationFrame(function(){
            var h=document.documentElement;
            var st=window.scrollY||h.scrollTop;
            var sh=(h.scrollHeight||document.body.scrollHeight)-h.clientHeight;
            if(!sh){_scrollTick=false;return;}
            var pct=Math.round(st/sh*100);
            for(var i=0;i<thresholds.length;i++){
                var t=thresholds[i];
                if(pct>=t&&!_scrollFired[t]){_scrollFired[t]=true;push('scroll',{event_value:String(t),event_label:t+'%'});}
            }
            _scrollTick=false;
        });
    },{passive:true});
}

// Time on page
if(S.time){
    function fireTime(){if(_timeFired)return;_timeFired=true;var sec=Math.round((Date.now()-_startTime)/1000);push('time_on_page',{event_value:String(sec),event_label:sec+'s'});_flush();}
    document.addEventListener('visibilitychange',function(){if(document.visibilityState==='hidden')fireTime();});
    window.addEventListener('beforeunload',fireTime);
}

// Outbound clicks — any hostname different from current site is external
if(S.outbound){
    document.addEventListener('click',function(e){
        var a=e.target.closest('a');
        if(!a||!a.href)return;
        try{var h=new URL(a.href).hostname;if(h&&h!==location.hostname){push('outbound_click',{event_label:a.href,event_data:JSON.stringify({text:a.textContent.trim().substring(0,200),domain:h})});}}catch(err){}
    });
}

// CTA click
document.addEventListener('click',function(e){
    var el=e.target.closest('[data-itp="cta_click"]');
    if(!el)return;
    push('cta_click',{event_label:el.textContent.trim().substring(0,200),event_data:JSON.stringify({tag:el.tagName,href:el.href||''})});
});

// Form submit
document.addEventListener('submit',function(e){
    var f=e.target.closest('[data-itp="form_submit"]');
    if(!f)return;
    push('form_submit',{event_label:f.id||f.action||'',event_data:JSON.stringify({id:f.id,action:f.action,method:f.method})});
});

// Search
if(S.search&&C.ctx&&C.ctx.wp_context==='search'){
    push('search',{event_label:C.ctx.search_query||'',event_value:String(C.ctx.search_results||0)});
}

// 404
if(S.e404&&C.ctx&&C.ctx.wp_context==='404'){
    push('error_404',{event_label:location.href,event_data:JSON.stringify({referrer:document.referrer||''})});
}

})();
