<?php
$locale=$locale??default_locale();
$li=locale_info($locale);
$sourceSiteName=setting('site_name',config('name'));
$siteName=translation_text('site','name',$locale,$locale==='ar'?$sourceSiteName:'Saradeeeb');
$sourceTagline=setting('tagline',config('tagline'));
$tagline=translation_text('site','tagline',$locale,$locale==='ar'?$sourceTagline:'Stories, mysteries and knowledge beyond the familiar.');
if(isset($title) && $locale!=='ar' && (string)$title===(string)$sourceSiteName) $title=$siteName;
$user=auth_user();
$canonical=$canonical??url($_SERVER['REQUEST_URI']??'/');
$description=$description??$tagline;
$brandColor=setting('brand_color','#157176') ?: '#157176';
$brandLogo=setting('brand_logo','');
$brandLogoHeight=max(34,min(110,(int)(setting('brand_logo_height','64')?:64)));
$showMark=setting('brand_mark_enabled','1')==='1';
$markText=setting('brand_mark_text','س') ?: 'س';
if($locale!=='ar' && preg_match('/[\x{0600}-\x{06FF}]/u',$markText)) $markText='S';
$markBg=setting('brand_mark_bg',$brandColor) ?: $brandColor;
$markColor=setting('brand_mark_color','#ffffff') ?: '#ffffff';
$languages=enabled_locales();

$tickerEnabled=setting('news_ticker_enabled','1')==='1';
$tickerItems=$tickerEnabled?news_ticker_items($locale,10):[];
$tickerLabels=['ar'=>'أهم المقالات','en'=>'Top stories','es'=>'Historias destacadas','fr'=>'Articles à la une','ru'=>'Главные истории'];
$tickerLabel=translation_text('layout','ticker_label',$locale,$tickerLabels[$locale]??$tickerLabels['en']);
$tickerSpeed