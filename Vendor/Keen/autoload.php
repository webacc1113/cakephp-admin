<?php
require_once __DIR__ . '/Guzzle/Common/HasDispatcherInterface.php';
require_once __DIR__ . '/Symfony/Component/EventDispatcher/EventDispatcherInterface.php';
require_once __DIR__ . '/Symfony/Component/EventDispatcher/EventDispatcher.php';
require_once __DIR__ . '/Guzzle/Common/ToArrayInterface.php';
require_once __DIR__ . '/Symfony/Component/EventDispatcher/Event.php';
require_once __DIR__ . '/Guzzle/Common/Event.php';
require_once __DIR__ . '/Guzzle/Common/AbstractHasDispatcher.php';
require_once __DIR__ . '/Guzzle/Common/FromConfigInterface.php';
require_once __DIR__ . '/Guzzle/Http/ClientInterface.php';
require_once __DIR__ . '/Guzzle/Http/Message/RequestFactoryInterface.php';
require_once __DIR__ . '/Guzzle/Http/Curl/CurlVersion.php';
require_once __DIR__ . '/Guzzle/Common/Version.php';
require_once __DIR__ . '/Guzzle/Http/Message/MessageInterface.php';
require_once __DIR__ . '/Guzzle/Http/Message/Header/HeaderFactoryInterface.php';
require_once __DIR__ . '/Guzzle/Http/Message/Header/HeaderInterface.php';
require_once __DIR__ . '/Guzzle/Http/Message/Header.php';
require_once __DIR__ . '/Guzzle/Http/Message/Header/CacheControl.php';
require_once __DIR__ . '/Guzzle/Http/Message/Header/HeaderFactory.php';
require_once __DIR__ . '/Guzzle/Http/Message/Header/HeaderCollection.php';
require_once __DIR__ . '/Guzzle/Http/Message/AbstractMessage.php';
require_once __DIR__ . '/Guzzle/Http/Message/RequestInterface.php';
require_once __DIR__ . '/Guzzle/Common/Exception/GuzzleException.php';
require_once __DIR__ . '/Guzzle/Common/Exception/RuntimeException.php';
require_once __DIR__ . '/Guzzle/Http/Exception/HttpException.php';
require_once __DIR__ . '/Guzzle/Http/Exception/RequestException.php';
require_once __DIR__ . '/Guzzle/Http/Exception/BadResponseException.php';
require_once __DIR__ . '/Guzzle/Http/Exception/ClientErrorResponseException.php';
require_once __DIR__ . '/Guzzle/Http/Message/Request.php';
require_once __DIR__ . '/Guzzle/Http/Message/RequestFactory.php';
require_once __DIR__ . '/Guzzle/Common/Collection.php';
require_once __DIR__ . '/Guzzle/Http/QueryAggregator/QueryAggregatorInterface.php';
require_once __DIR__ . '/Guzzle/Http/QueryAggregator/PhpAggregator.php';
require_once __DIR__ . '/Guzzle/Http/QueryString.php';
require_once __DIR__ . '/Guzzle/Http/Url.php';
require_once __DIR__ . '/Guzzle/Parser/UriTemplate/UriTemplateInterface.php';
require_once __DIR__ . '/Guzzle/Parser/UriTemplate/UriTemplate.php';
require_once __DIR__ . '/Guzzle/Parser/ParserRegistry.php';
require_once __DIR__ . '/Guzzle/Stream/StreamInterface.php';
require_once __DIR__ . '/Guzzle/Stream/Stream.php';
require_once __DIR__ . '/Guzzle/Http/EntityBodyInterface.php';
require_once __DIR__ . '/Guzzle/Http/EntityBody.php';
require_once __DIR__ . '/Guzzle/Http/Message/Response.php';
require_once __DIR__ . '/Guzzle/Http/Curl/RequestMediator.php';
require_once __DIR__ . '/Guzzle/Http/Curl/CurlHandle.php';
require_once __DIR__ . '/Guzzle/Http/Curl/CurlMultiInterface.php';
require_once __DIR__ . '/Guzzle/Common/Exception/ExceptionCollection.php';
require_once __DIR__ . '/Guzzle/Http/Exception/MultiTransferException.php';
require_once __DIR__ . '/Guzzle/Http/Exception/CurlException.php';
require_once __DIR__ . '/Guzzle/Http/Curl/CurlMulti.php';
require_once __DIR__ . '/Guzzle/Http/Curl/CurlMultiProxy.php';
require_once __DIR__ . '/Guzzle/Http/Client.php';
require_once __DIR__ . '/Guzzle/Service/ClientInterface.php';
require_once __DIR__ . '/Guzzle/Service/Command/Factory/FactoryInterface.php';
require_once __DIR__ . '/Guzzle/Service/Command/CommandInterface.php';
require_once __DIR__ . '/Guzzle/Service/Description/ValidatorInterface.php';
require_once __DIR__ . '/Guzzle/Service/Description/SchemaValidator.php';
require_once __DIR__ . '/Guzzle/Service/Exception/ValidationException.php';
require_once __DIR__ . '/Guzzle/Service/Command/AbstractCommand.php';
require_once __DIR__ . '/Guzzle/Service/Command/RequestSerializerInterface.php';
require_once __DIR__ . '/Guzzle/Service/Command/LocationVisitor/Request/RequestVisitorInterface.php';
require_once __DIR__ . '/Guzzle/Service/Command/LocationVisitor/Request/AbstractRequestVisitor.php';
require_once __DIR__ . '/Guzzle/Service/Command/LocationVisitor/Request/HeaderVisitor.php';
require_once __DIR__ . '/Guzzle/Service/Command/LocationVisitor/Request/QueryVisitor.php';
require_once __DIR__ . '/Guzzle/Service/Command/LocationVisitor/VisitorFlyweight.php';
require_once __DIR__ . '/Guzzle/Service/Command/DefaultRequestSerializer.php';
require_once __DIR__ . '/Guzzle/Service/Command/ResponseParserInterface.php';
require_once __DIR__ . '/Guzzle/Service/Command/DefaultResponseParser.php';
require_once __DIR__ . '/Guzzle/Service/Command/OperationResponseParser.php';
require_once __DIR__ . '/Guzzle/Service/Command/OperationCommand.php';
require_once __DIR__ . '/Guzzle/Service/Command/Factory/ServiceDescriptionFactory.php';
require_once __DIR__ . '/Guzzle/Inflection/InflectorInterface.php';
require_once __DIR__ . '/Guzzle/Inflection/MemoizingInflector.php';
require_once __DIR__ . '/Guzzle/Inflection/Inflector.php';
require_once __DIR__ . '/Guzzle/Service/Command/Factory/ConcreteClassFactory.php';
require_once __DIR__ . '/Guzzle/Service/Command/Factory/CompositeFactory.php';
require_once __DIR__ . '/Guzzle/Service/Client.php';
require_once __DIR__ . '/Symfony/Component/EventDispatcher/EventSubscriberInterface.php';
require_once __DIR__ . '/Guzzle/Http/RedirectPlugin.php';
require_once __DIR__ . '/Guzzle/Service/Description/ServiceDescriptionInterface.php';
require_once __DIR__ . '/Guzzle/Service/ConfigLoaderInterface.php';
require_once __DIR__ . '/Guzzle/Service/AbstractConfigLoader.php';
require_once __DIR__ . '/Guzzle/Service/Description/ServiceDescriptionLoader.php';
require_once __DIR__ . '/Guzzle/Service/Description/OperationInterface.php';
require_once __DIR__ . '/Client/Filter/MultiTypeFiltering.php';
require_once __DIR__ . '/Guzzle/Service/Description/Parameter.php';
require_once __DIR__ . '/Guzzle/Service/Description/Operation.php';
require_once __DIR__ . '/Guzzle/Service/Description/ServiceDescription.php';
require_once __DIR__ . '/Client/KeenIOClient.php';











