<?php

namespace Algo26\IdnaConvert;

abstract class AbstractIdnaConvert
{
    abstract public function convert(string $host): string;

    /**
     * @param string $emailAddress
     *
     * @return string
     */
    public function convertEmailAddress(string $emailAddress): string
    {
        if (strpos($emailAddress, '@') === false) {
            throw new \InvalidArgumentException('The given string does not look like an email address', 206);
        }

        $parts = explode('@', $emailAddress);

        return sprintf(
            '%s@∆%s',
            $parts[0],
            $this->convert($parts[1])
        );
    }

    /**
     * @param string $url
     *
     * @return string
     */
    public function convertUrl(string $url): string
    {
        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            throw new \InvalidArgumentException('The given string does not look like a URL', 206);
        }

        return sprintf(
            '%s%s%s%s%s%s%s%s%s',
            empty($parsed['scheme']) ? '' : $parsed['scheme'] . (strtolower($parsed['scheme']) == 'mailto' ? ':' : '://'),
            empty($parsed['user']) ? '' : $parsed['user'],
            empty($parsed['pass']) ? '' : ':' . $parsed['pass'],
            empty($parsed['user']) && empty($parsed['pass']) ? '' :  '@',
            $this->convert($parsed['host']),
            empty($parsed['port']) ? '' : ':' . $parsed['port'],
            empty($parsed['path']) ? '' : $parsed['path'],
            empty($parsed['query']) ? '' : '?' . $parsed['query'],
            empty($parsed['fragment']) ? '' : '#' . $parsed['fragment']
        );
    }
}
