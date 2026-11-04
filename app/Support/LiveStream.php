<?php

namespace App\Support;

class LiveStream
{
    /**
     * Turn a pasted stream link into something the Live TV page can play.
     *
     * @return array{type: 'iframe'|'hls'|'video', src: string}|null
     */
    public static function fromUrl(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $host = strtolower(preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)));
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com'], true)) {
            $embed = 'https://www.youtube-nocookie.com/embed/';
            $options = 'autoplay=1&mute=1&rel=0&modestbranding=1';

            if (preg_match('#^/channel/(UC[\w-]+)#', $path, $match)) {
                return ['type' => 'iframe', 'src' => "{$embed}live_stream?channel={$match[1]}&{$options}"];
            }

            $id = $host === 'youtu.be'
                ? ltrim($path, '/')
                : ($query['v'] ?? (preg_match('#^/(?:live|embed|shorts)/([\w-]+)#', $path, $match) ? $match[1] : null));

            return $id ? ['type' => 'iframe', 'src' => $embed.$id.'?'.$options] : null;
        }

        if ($host === 'vimeo.com' && preg_match('#^/event/(\d+)#', $path, $match)) {
            return ['type' => 'iframe', 'src' => "https://vimeo.com/event/{$match[1]}/embed?autoplay=1&muted=1"];
        }

        if ($host === 'vimeo.com' && preg_match('#^/(\d+)#', $path, $match)) {
            return ['type' => 'iframe', 'src' => "https://player.vimeo.com/video/{$match[1]}?autoplay=1&muted=1"];
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match (true) {
            $extension === 'm3u8' => ['type' => 'hls', 'src' => $url],
            in_array($extension, ['mp4', 'webm', 'ogg'], true) => ['type' => 'video', 'src' => $url],
            default => ['type' => 'iframe', 'src' => $url],
        };
    }
}
