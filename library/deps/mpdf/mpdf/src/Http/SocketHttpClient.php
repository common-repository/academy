<?php
/**
 * @license GPL-2.0-only
 *
 * Modified by Kodezen on 03-October-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Academy\Mpdf\Http;

use Academy\Mpdf\Log\Context as LogContext;
use Academy\Mpdf\PsrHttpMessageShim\Response;
use Academy\Mpdf\PsrHttpMessageShim\Stream;
use Academy\Mpdf\PsrLogAwareTrait\PsrLogAwareTrait;
use Academy\Psr\Http\Message\RequestInterface;
use Academy\Psr\Log\LoggerInterface;

class SocketHttpClient implements \Academy\Mpdf\Http\ClientInterface, \Academy\Psr\Log\LoggerAwareInterface
{

	use PsrLogAwareTrait;

	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	public function sendRequest(RequestInterface $request)
	{
		if (null === $request->getUri()) {
			return (new Response()); // @todo throw exception
		}

		$url = $request->getUri();

		if (is_string($url)) {
			$url = new Uri($url);
		}

		$timeout = 1;

		$file = $url->getPath() ?: '/';
		$scheme = $url->getScheme();
		$port = $url->getPort() ?: 80;
		$prefix = '';

		if ($scheme === 'https') {
			$prefix = 'ssl://';
			$port = $url->getPort() ?: 443;
		}

		$query = $url->getQuery();
		if ($query) {
			$file .= '?' . $query;
		}

		$socketPath = $prefix . $url->getHost();

		$this->logger->debug(sprintf('Opening socket on %s:%s of URL "%s"', $socketPath, $port, $request->getUri()), ['context' => LogContext::REMOTE_CONTENT]);

		$response = new Response();

		if (!($fh = @fsockopen($socketPath, $port, $errno, $errstr, $timeout))) {
			$this->logger->error(sprintf('Socket error "%s": "%s"', $errno, $errstr), ['context' => LogContext::REMOTE_CONTENT]);

			return $response;
		}

		$getRequest = 'GET ' . $file . ' HTTP/1.1' . "\r\n" .
			'Host: ' . $url->getHost() . " \r\n" .
			'Connection: close' . "\r\n\r\n";

		fwrite($fh, $getRequest);

		$httpHeader = fgets($fh, 1024);
		if (!$httpHeader) {
			return $response; // @todo throw exception
		}

		preg_match('@HTTP/(?P<protocolVersion>[\d\.]+) (?P<httpStatusCode>[\d]+) .*@', $httpHeader, $parsedHeader);

		if (!$parsedHeader) {
			return $response; // @todo throw exception
		}

		$response = $response->withStatus($parsedHeader['httpStatusCode']);

		while (!feof($fh)) {
			$s = fgets($fh, 1024);
			if ($s === "\r\n") {
				break;
			}
			preg_match('/^(?P<headerName>.*?): ?(?P<headerValue>.*)$/', $s, $parsedHeader);
			if (!$parsedHeader) {
				continue;
			}
			$response = $response->withHeader($parsedHeader['headerName'], trim($parsedHeader['headerValue']));
		}

		$body = '';

		while (!feof($fh)) {
			$line = fgets($fh, 1024);
			$body .= $line;
		}

		fclose($fh);

		$stream = Stream::create($body);
		$stream->rewind();

		return $response
			->withBody($stream);
	}

}