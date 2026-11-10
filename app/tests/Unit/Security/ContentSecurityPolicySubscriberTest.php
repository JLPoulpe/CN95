<?php

namespace App\Tests\Unit\Security;

use App\Security\ContentSecurityPolicySubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ContentSecurityPolicySubscriberTest extends TestCase
{
    public function testLaPolitiqueInterditLesScriptsEnLigneSansNonce(): void
    {
        $kernel = $this->createStub(KernelInterface::class);
        $request = new Request();
        $sub = new ContentSecurityPolicySubscriber();

        $sub->surRequete(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $nonce = $request->attributes->get(ContentSecurityPolicySubscriber::ATTRIBUT);
        $this->assertNotEmpty($nonce);

        $reponse = new Response();
        $sub->surReponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $reponse));
        $csp = (string) $reponse->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'nonce-".$nonce."'", $csp);
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-inline'/", $csp);
        foreach (["object-src 'none'", "base-uri 'self'", "form-action 'self'", "frame-ancestors 'self'"] as $directive) {
            $this->assertStringContainsString($directive, $csp);
        }
    }

    public function testAutresEnTetesDeSecurite(): void
    {
        $kernel = $this->createStub(KernelInterface::class);
        $sub = new ContentSecurityPolicySubscriber();
        $http = new Request();
        $https = Request::create('https://exemple.test/');
        $reponses = [];
        foreach ([$http, $https] as $i => $requete) {
            $sub->surRequete(new RequestEvent($kernel, $requete, HttpKernelInterface::MAIN_REQUEST));
            $reponses[$i] = new Response();
            $sub->surReponse(new ResponseEvent($kernel, $requete, HttpKernelInterface::MAIN_REQUEST, $reponses[$i]));
        }

        $this->assertSame('nosniff', $reponses[0]->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $reponses[0]->headers->get('X-Frame-Options'));
        $this->assertFalse($reponses[0]->headers->has('Strict-Transport-Security'));
        $this->assertTrue($reponses[1]->headers->has('Strict-Transport-Security'));
    }

    public function testNonceDifferentParRequete(): void
    {
        $kernel = $this->createStub(KernelInterface::class);
        $sub = new ContentSecurityPolicySubscriber();
        $a = new Request();
        $b = new Request();
        $sub->surRequete(new RequestEvent($kernel, $a, HttpKernelInterface::MAIN_REQUEST));
        $sub->surRequete(new RequestEvent($kernel, $b, HttpKernelInterface::MAIN_REQUEST));

        $this->assertNotSame($a->attributes->get(ContentSecurityPolicySubscriber::ATTRIBUT), $b->attributes->get(ContentSecurityPolicySubscriber::ATTRIBUT));
    }
}
