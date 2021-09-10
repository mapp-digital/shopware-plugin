<?php declare(strict_types=1);

namespace Mapp\Connect\Shopware\Subscriber;

use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Content\Newsletter\NewsletterEvents;
use Mapp\Connect\Shopware\Service\MappConnectService;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Event\BusinessEvents;
use Shopware\Core\Framework\Event\BusinessEvent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Mapp\Connect\Shopware\Entity\MappEventDefinition;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Mapp\Connect\Shopware\Entity\MappEventCollection;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class MappConnectSubscriber implements EventSubscriberInterface
{
    private $mappConnectService;

    private $mappEventDefinition;

    private $definitionRegistry;

    public function __construct(
        MappConnectService $mappConnectService,
        DefinitionInstanceRegistry $definitionRegistry,
        MappEventDefinition $mappEventDefinition,
        EntityRepositoryInterface $productRepository,
        EntityRepositoryInterface $productManufacturerRepository,
        EntityRepositoryInterface $languageRepository)
    {
        $this->mappConnectService = $mappConnectService;
        $this->definitionRegistry = $definitionRegistry;
        $this->mappEventDefinition = $mappEventDefinition;
        $this->productRepository = $productRepository;
        $this->productManufacturerRepository = $productManufacturerRepository;
        $this->languageRepository = $languageRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NewsletterEvents::NEWSLETTER_RECIPIENT_WRITTEN_EVENT => 'onNewsletterChange',
            GuestCustomerRegisterEvent::class => 'onGuestRegister',
            CustomerRegisterEvent::class => 'onCustomerRegister',
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
            BusinessEvents::GLOBAL_EVENT => 'onBusinessEvent'
        ];
    }

    public static $fieldsForEvents = array(
        CustomerAccountRecoverRequestEvent::class => ['resetUrl', 'shopName'],
        ContactFormEvent::class => ['contactFormData.comment'],
        CustomerRegisterEvent::class => ['customer.customerNumber', 'customer.firstName',
            'customer.lastName', 'customer.title', 'customer.birthday'],
        GuestCustomerRegisterEvent::class => ['customer.customerNumber', 'customer.firstName',
            'customer.lastName', 'customer.title', 'customer.birthday'],
        OrderStateMachineStateChangeEvent::class => []
    );

    public function onNewsletterChange(EntityWrittenEvent $event)
    {
        $doi = $this->mappConnectService->isEnable('dataNewsletterDoi');
        if ($this->mappConnectService->isEnable('dataNewsletter') || $doi) {
            foreach ($event->getPayloads() as $data) {
                $mcdata = [
                    'email' => $data['email'],
                    'title' => $data['title'],
                    'firstName' => $data['firstName'],
                    'lastName' => $data['lastName'],
                    'languageId' => $data['languageId'],
                    'zipCode' => $data['zipCode'],
                    'group' => $this->mappConnectService->getGroup('Newsletter')
                ];

                if ($data['status'] == 'optOut') {
                    $mcdata['unsubscribe'] = true;
                } else {
                    if ($doi) {
                        $mcdata['doubleOptIn'] = true;
                    }
                }

                if ($mc = $this->mappConnectService->getMappConnectClient()) {
                    $mc->event('newsletter', $mcdata);
                }
            }
        }
    }

    public function onCustomerRegister(CustomerRegisterEvent $event)
    {
        if ($this->mappConnectService->isEnable('dataCustomers')) {
            $customer = $event->getCustomer();
            $data = [
                'email' => $customer->getEmail(),
                'customerNumber' => $customer->getCustomerNumber(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'title' => $customer->getTitle(),
                'birthday' => $customer->getBirthday(),
                'group' => $this->mappConnectService->getGroup('Customers')
            ];

            /*$language = $this->languageRepository->search(new Criteria([$customer->getLanguageId()]), $event->getContext())->first();
            if (!is_null($language)) {
                $data['languageId'] = $language->getLocaleId();
                $data['languageName'] = $language->getName();
            }*/

            if ($mc = $this->mappConnectService->getMappConnectClient()) {
                $mc->event('user', $data);
            }
        }
    }

    public function onGuestRegister(GuestCustomerRegisterEvent $event)
    {
        if ($this->mappConnectService->isEnable('dataGuests')) {
            $customer = $event->getCustomer();
            $data = [
                'email' => $customer->getEmail(),
                'customerNumber' => $customer->getCustomerNumber(),
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'title' => $customer->getTitle(),
                'birthday' => $customer->getBirthday(),
                'group' => $this->mappConnectService->getGroup('Guests')
            ];

            if ($mc = $this->mappConnectService->getMappConnectClient()) {
                $mc->event('guest', $data);
            }
        }
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event)
    {
        $mc = $this->mappConnectService->getMappConnectClient();
        if (is_null($mc))
            return;

        $data = $this->getOrderData($event);

        $mc->event('transaction', $data);
    }

    private function getOrderData(CheckoutOrderPlacedEvent $event) {
        $order = $event->getOrder();
        $data = [
            'orderNumber' => $order->getOrderNumber(),
            'orderedAt' => $order->getOrderDateTime()->format('Y-m-d H:i:s'),
            'email' => $order->getOrderCustomer()->getEmail(),
            'campaignCode' => $order->getCampaignCode(),
            'shippingTotal' => strval($order->getShippingTotal()),
            'amountTotal' => strval($order->getAmountTotal()),
            'shippingCosts' => strval($order->getShippingCosts()->getTotalPrice()),
            'customerComment' => $order->getCustomerComment(),
            'affiliateCode' => $order->getAffiliateCode()
        ];

        if (!is_null($order->getCurrency())) {
            $data['currency'] = $order->getCurrency()->getIsoCode();
        }

        if ($order->getOrderCustomer()->getCustomer()->getGuest()) {
            if (!$this->mappConnectService->isEnable('dataOrderGuests'))
                return;
            $data['guest'] = true;
        } else {
            if (!$this->mappConnectService->isEnable('dataOrderCustomers'))
                return;
        }

        $data['items'] = $this->getOrderItems($order, $event->getContext());

        $mappevents = $this->getMappEvents($event, $event->getContext());
        if (!is_null($mappevents) && $mappevents->count() > 0) {
            foreach ($mappevents as $mevent) {
                $data['messageId'] = $mevent->getMessageId();
            }
        }

        return $data;
    }

    private function getOrderItems(OrderEntity $orderEntity, Context $context) {
        $items = array();
        foreach ($orderEntity->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE)
                continue;

            $product = $this->productRepository->search(new Criteria([$lineItem->getProductId()]), $context)->first();

            $item['quantity'] = $lineItem->getQuantity();
            $item['unitPrice'] = strval($lineItem->getUnitPrice());

            if (!is_null($product)) {
                $item['productId'] = $product->getProductNumber();
                $item['name'] = $product->getName();
                $item['description'] = $product->getDescription();

                if (!is_null($product->getManufacturerId())) {
                    $productManufacturer = $this->productManufacturerRepository->search(new Criteria([$product->getManufacturerId()]), $context)->first();
                    $item['manufacturerName'] = $productManufacturer->getName();
                }
            }

            array_push($items, $item);
        }
        return $items;
    }

    private function getMappEvents(BusinessEventInterface $event, Context $context): MappEventCollection
    {
        $name = $event->getName();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mapp_event.eventName', $name));
        $criteria->addFilter(new EqualsFilter('mapp_event.active', true));

        if ($event instanceof SalesChannelAware) {
            $criteria->addFilter(new OrFilter([
                new EqualsFilter('salesChannels.id', $event->getSalesChannelId()),
                new EqualsFilter('salesChannels.id', null),
            ]));
        }

        $events = $this->definitionRegistry
            ->getRepository($this->mappEventDefinition->getEntityName())
            ->search($criteria, $context)
            ->getEntities();

        return $events;
    }

    public function onBusinessEvent(BusinessEvent $event)
    {

        if (!$this->shouldTriggerDefaultLogic($event)) {
            return;
        }

        $innerEvent = $event->getEvent();
        error_log("Event " . $innerEvent->getName() . " class " . get_class($innerEvent));

        $userEmailFromEvent = $this->getUserEmailFromEvent($event);

        $mc = $this->mappConnectService->getMappConnectClient();
        if (is_null($mc))
            return;

        $mappevents = $this->getMappEvents($innerEvent, $innerEvent->getContext());

        if ($mappevents->count() <= 0)
            return;

        $data = [
            'externalEvent' => $innerEvent->getName(),
            'email' => $userEmailFromEvent
        ];

        $type = 'automation';

        // data for this event can't be extracted this way
        if (!$innerEvent instanceof OrderStateMachineStateChangeEvent) {
            $eventData = $this->extractEventData($event);
            $eventDataToBeSent = $this->getDataToBeSentForEvent($eventData, $event);
            foreach ($eventDataToBeSent as $key => $value) {
                $data[$key] = $value;
            }
        }

        if ($innerEvent instanceof CustomerRegisterEvent) {
            $data['group'] = $this->mappConnectService->getGroup('Customers');
        } elseif ($innerEvent instanceof GuestCustomerRegisterEvent) {
            $data['group'] = $this->mappConnectService->getGroup('Guests');
        } elseif ($innerEvent instanceof OrderStateMachineStateChangeEvent) {
            $data['order.orderNumber'] = $innerEvent->getOrder()->getOrderNumber();
            try {
                $data['order.orderCustomer.firstName'] = $innerEvent->getOrder()->getOrderCustomer()->getFirstName();
                $data['order.orderCustomer.lastName'] = $innerEvent->getOrder()->getOrderCustomer()->getLastName();
                $data['order.orderCustomer.title'] = $innerEvent->getOrder()->getOrderCustomer()->getTitle();
                $data['order.orderCustomer.salutation.displayName'] = $innerEvent->getOrder()->getOrderCustomer()->getSalutation()->getDisplayName();
                $data['order.stateMachineState.name'] = $innerEvent->getOrder()->getStateMachineState()->getName();
             } catch (\Throwable $t) {
                error_log($t->getMessage());
            }
            $data['items'] = $this->getOrderItems($innerEvent->getOrder(), $innerEvent->getContext());

            /*
            'order.stateMachineState.name'
             */
        }

        foreach ($mappevents as $mevent) {
            if ($mevent->getMessageId() > 0) {
                $type = 'email';
                $data['messageId'] = $mevent->getMessageId();
            }
            $mc->event($type, $data);
        }

    }

    private function shouldTriggerDefaultLogic(BusinessEvent $event)
    {
        $innerEvent = $event->getEvent();
        return !($innerEvent instanceof EntityWrittenEvent || $innerEvent instanceof CheckoutOrderPlacedEvent);
    }

    private function getUserEmailFromEvent(BusinessEvent $event) {
        $innerEvent = $event->getEvent();
        if ($innerEvent instanceof CustomerAccountRecoverRequestEvent) {
            return $innerEvent->getCustomerRecovery()->getCustomer()->getEmail();
        } elseif ($innerEvent instanceof UserRecoveryRequestEvent) {
            return $innerEvent->getUserRecovery()->getUser()->getEmail();
        } elseif ($innerEvent instanceof OrderStateMachineStateChangeEvent) {
            return $innerEvent->getOrder()->getOrderCustomer()->getEmail();
        } elseif ($innerEvent instanceof ContactFormEvent) {
            return $innerEvent->getContactFormData()['email'];
        } elseif ($innerEvent instanceof CustomerRegisterEvent) {
            return $innerEvent->getCustomer()->getEmail();
        } elseif ($innerEvent instanceof GuestCustomerRegisterEvent) {
            return $innerEvent->getCustomer()->getEmail();
        } elseif ($innerEvent instanceof CheckoutOrderPlacedEvent) {
            return $innerEvent->getOrder()->getOrderCustomer()->getEmail();
        }
        else {
            return "unknown";
        }
    }

    private function getDataToBeSentForEvent(array $eventData, BusinessEvent $event) {
        $result = array();
        $innerEvent = $event->getEvent();
        $fieldsToBeExtracted = self::$fieldsForEvents[get_class($innerEvent)];
        foreach ($fieldsToBeExtracted as $key => $value) {
            $result[$value] = $eventData[$value];
        }
        return $result;
    }

    private function extractEventData(BusinessEvent $event) {
        $result = array();
        $innerEvent = $event->getEvent();
        try {
            $normalizer = new ObjectNormalizer();
            $encoder = new JsonEncoder();

            $serializer = new Serializer([$normalizer], [$encoder]);

            $ser = $serializer->serialize($innerEvent, 'json', [AbstractNormalizer::IGNORED_ATTRIBUTES => ['language',
                'translations', 'registrationTitle', 'registrationIntroduction', 'registrationOnlyCompanyRegistration',
                'registrationSeoMetaDescription']]);
            error_log("SER " . $ser);

            $arrayData = $serializer->normalize($innerEvent, 'json', [AbstractNormalizer::IGNORED_ATTRIBUTES => ['language',
                'translations', 'registrationTitle', 'registrationIntroduction', 'registrationOnlyCompanyRegistration',
                'registrationSeoMetaDescription']]);

            foreach (array_keys($innerEvent->getAvailableData()->toArray()) as $key) {
                $result[$key] = $arrayData[$key];
            }

            $result = $this->flattenArray($result);
            foreach (array_keys($result) as $key) {
                error_log($key . ' - ' . $result[$key]);
            }
        } catch (\Throwable $t) {
            error_log($t->getMessage());
            $result['error'] = $t->getMessage();
        }
        return $result;
    }

    private function flattenArray($array, $prefix = '')
    {
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = $result + $this->flattenArray($value, $prefix . $key . '.');
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }
}
