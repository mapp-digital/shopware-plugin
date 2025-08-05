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
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Mapp\Connect\Shopware\Entity\MappEventDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Mapp\Connect\Shopware\Entity\MappEventCollection;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Symfony\Contracts\EventDispatcher\Event;

class MappConnectSubscriber implements EventSubscriberInterface
{
    /**
     * @var MappConnectService
     */
    private $mappConnectService;

    private $mappEventDefinition;

    private $definitionRegistry;
    private $productRepository;
    private $productManufacturerRepository;
    private $languageRepository;

    public function __construct(
        MappConnectService $mappConnectService,
        DefinitionInstanceRegistry $definitionRegistry,
        MappEventDefinition $mappEventDefinition,
        EntityRepository $productRepository,
        EntityRepository $productManufacturerRepository,
        EntityRepository $languageRepository)
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
            NewsletterConfirmEvent::class => 'onNewsletterConfirm',
            GuestCustomerRegisterEvent::class => 'onGuestRegister',
            CustomerRegisterEvent::class => 'onCustomerRegister',
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',

            // BusinessEvents::GLOBAL_EVENT
            'Shopware\\Core\\Checkout\\Customer\\Event\\DoubleOptInGuestOrderEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Checkout\\Customer\\Event\\CustomerDoubleOptInRegistrationEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Checkout\\Customer\\Event\\CustomerLoginEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Checkout\\Customer\\Event\\CustomerLogoutEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Checkout\\Order\\Event\\OrderPaymentMethodChangedEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Content\\ContactForm\\Event\\ContactFormEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Checkout\\Customer\\Event\\CustomerGroupRegistrationAccepted' => 'onBusinessEvent',
            'Shopware\\Core\\Checkout\\Customer\\Event\\CustomerGroupRegistrationDeclined' => 'onBusinessEvent',
            'Shopware\\Core\\Checkout\\Customer\\Event\\CustomerAccountRecoverRequestEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Content\\MailTemplate\\Service\\Event\\MailBeforeSentEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Content\\MailTemplate\\Service\\Event\\MailBeforeValidateEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Content\\MailTemplate\\Service\\Event\\MailSentEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Content\\Newsletter\\Event\\NewsletterRegisterEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Content\\Newsletter\\Event\\NewsletterUnsubscribeEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Content\\ProductExport\\Event\\ProductExportLoggingEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Content\\Product\\SalesChannel\\Review\\Event\\ReviewFormEvent' => 'onBusinessEvent',
            'Shopware\\Core\\Checkout\\Order\\Event\\OrderStateMachineStateChangeEvent' => 'onBusinessEvent',
            'Shopware\\Core\\System\\User\\Recovery\\UserRecoveryRequestEvent' => 'onBusinessEvent',

            'checkout.customer.before.login' => 'onBusinessEvent',
            'checkout.customer.changed-payment-method' => 'onBusinessEvent',
            'checkout.customer.deleted' => 'onBusinessEvent',
            'checkout.customer.double_opt_in_guest_order' => 'onBusinessEvent',
            'checkout.customer.double_opt_in_registration' => 'onBusinessEvent',
            'checkout.customer.login' => 'onBusinessEvent',
            'checkout.customer.logout' => 'onBusinessEvent',
            'checkout.order.payment_method.changed' => 'onBusinessEvent',
            'contact_form.send' => 'onBusinessEvent',
            'customer.group.registration.accepted' => 'onBusinessEvent',
            'customer.group.registration.declined' => 'onBusinessEvent',
            'customer.recovery.request' => 'onBusinessEvent',
            'mail.after.create.message' => 'onBusinessEvent',
            'mail.before.send' => 'onBusinessEvent',
            'mail.sent' => 'onBusinessEvent',
            'newsletter.register' => 'onBusinessEvent',
            'newsletter.unsubscribe' => 'onBusinessEvent',
            'product_export.log' => 'onBusinessEvent',
            'review_form.send' => 'onBusinessEvent',
            'state_enter.order.state.cancelled' => 'onBusinessEvent',
            'state_enter.order.state.completed' => 'onBusinessEvent',
            'state_enter.order.state.in_progress' => 'onBusinessEvent',
            'state_enter.order.state.open' => 'onBusinessEvent',
            'state_enter.order_delivery.state.cancelled' => 'onBusinessEvent',
            'state_enter.order_delivery.state.open' => 'onBusinessEvent',
            'state_enter.order_delivery.state.returned' => 'onBusinessEvent',
            'state_enter.order_delivery.state.returned_partially' => 'onBusinessEvent',
            'state_enter.order_delivery.state.shipped' => 'onBusinessEvent',
            'state_enter.order_delivery.state.shipped_partially' => 'onBusinessEvent',
            'state_enter.order_transaction.state.authorized' => 'onBusinessEvent',
            'state_enter.order_transaction.state.cancelled' => 'onBusinessEvent',
            'state_enter.order_transaction.state.chargeback' => 'onBusinessEvent',
            'state_enter.order_transaction.state.failed' => 'onBusinessEvent',
            'state_enter.order_transaction.state.in_progress' => 'onBusinessEvent',
            'state_enter.order_transaction.state.open' => 'onBusinessEvent',
            'state_enter.order_transaction.state.paid' => 'onBusinessEvent',
            'state_enter.order_transaction.state.paid_partially' => 'onBusinessEvent',
            'state_enter.order_transaction.state.refunded' => 'onBusinessEvent',
            'state_enter.order_transaction.state.refunded_partially' => 'onBusinessEvent',
            'state_enter.order_transaction.state.reminded' => 'onBusinessEvent',
            'state_enter.order_transaction.state.unconfirmed' => 'onBusinessEvent',
            'state_enter.order_transaction_capture.state.completed' => 'onBusinessEvent',
            'state_enter.order_transaction_capture.state.failed' => 'onBusinessEvent',
            'state_enter.order_transaction_capture.state.pending' => 'onBusinessEvent',
            'state_enter.order_transaction_capture_refund.state.cancelled' => 'onBusinessEvent',
            'state_enter.order_transaction_capture_refund.state.completed' => 'onBusinessEvent',
            'state_enter.order_transaction_capture_refund.state.failed' => 'onBusinessEvent',
            'state_enter.order_transaction_capture_refund.state.in_progress' => 'onBusinessEvent',
            'state_enter.order_transaction_capture_refund.state.open' => 'onBusinessEvent',
            'state_leave.order.state.cancelled' => 'onBusinessEvent',
            'state_leave.order.state.completed' => 'onBusinessEvent',
            'state_leave.order.state.in_progress' => 'onBusinessEvent',
            'state_leave.order.state.open' => 'onBusinessEvent',
            'state_leave.order_delivery.state.cancelled' => 'onBusinessEvent',
            'state_leave.order_delivery.state.open' => 'onBusinessEvent',
            'state_leave.order_delivery.state.returned' => 'onBusinessEvent',
            'state_leave.order_delivery.state.returned_partially' => 'onBusinessEvent',
            'state_leave.order_delivery.state.shipped' => 'onBusinessEvent',
            'state_leave.order_delivery.state.shipped_partially' => 'onBusinessEvent',
            'state_leave.order_transaction.state.authorized' => 'onBusinessEvent',
            'state_leave.order_transaction.state.cancelled' => 'onBusinessEvent',
            'state_leave.order_transaction.state.chargeback' => 'onBusinessEvent',
            'state_leave.order_transaction.state.failed' => 'onBusinessEvent',
            'state_leave.order_transaction.state.in_progress' => 'onBusinessEvent',
            'state_leave.order_transaction.state.open' => 'onBusinessEvent',
            'state_leave.order_transaction.state.paid' => 'onBusinessEvent',
            'state_leave.order_transaction.state.paid_partially' => 'onBusinessEvent',
            'state_leave.order_transaction.state.refunded' => 'onBusinessEvent',
            'state_leave.order_transaction.state.refunded_partially' => 'onBusinessEvent',
            'state_leave.order_transaction.state.reminded' => 'onBusinessEvent',
            'state_leave.order_transaction.state.unconfirmed' => 'onBusinessEvent',
            'state_leave.order_transaction_capture.state.completed' => 'onBusinessEvent',
            'state_leave.order_transaction_capture.state.failed' => 'onBusinessEvent',
            'state_leave.order_transaction_capture.state.pending' => 'onBusinessEvent',
            'state_leave.order_transaction_capture_refund.state.cancelled' => 'onBusinessEvent',
            'state_leave.order_transaction_capture_refund.state.completed' => 'onBusinessEvent',
            'state_leave.order_transaction_capture_refund.state.failed' => 'onBusinessEvent',
            'state_leave.order_transaction_capture_refund.state.in_progress' => 'onBusinessEvent',
            'state_leave.order_transaction_capture_refund.state.open' => 'onBusinessEvent',
            'user.recovery.request' => 'onBusinessEvent'
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
        $nle = $this->mappConnectService->isEnable('dataNewsletter');
        $mc = $this->mappConnectService->getMappConnectClient();

        if ($mc && ($nle || $doi)) {
            foreach ($event->getPayloads() as $data) {

                if ($data['status'] == NewsletterSubscribeRoute::STATUS_OPT_OUT) {
                    $mc->event('newsletter', [
                        'email' => $data['email'],
                        'group' => $this->mappConnectService->getGroup('Newsletter'),
                        'unsubscribe' => true
                    ]);
                }
            }
        }

        $this->onBusinessEvent($event);
    }

    public function onNewsletterConfirm(NewsletterConfirmEvent $event)
    {
        $doi = $this->mappConnectService->isEnable('dataNewsletterDoi');
        $nle = $this->mappConnectService->isEnable('dataNewsletter');
        $mc = $this->mappConnectService->getMappConnectClient();
        if ($mc && ($nle || $doi)) {
            $data = $event->getNewsletterRecipient();
            $mcdata = [
                'email' => $data->getEmail(),
                'firstName' => $data->getFirstName(),
                'lastName' => $data->getLastName(),
                'zipCode' => $data->getZipCode(),
                'city' => $data->getCity(),
                'group' => $this->mappConnectService->getGroup('Newsletter')
            ];
            if ($doi)
                $mcdata['doubleOptIn'] = true;
            $mc->event('newsletter', $mcdata);
        }

        $this->onBusinessEvent($event);
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

            if ($mc = $this->mappConnectService->getMappConnectClient()) {
                $mc->event('user', $data);
            }
        }

        $this->onBusinessEvent($event);
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
                $mc->event('user', $data);
            }
        }

        $this->onBusinessEvent($event);
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event)
    {
        $mc = $this->mappConnectService->getMappConnectClient();
        if (is_null($mc))
            return;

        $data = $this->getOrderData($event);
        if (is_null($data))
            return;

        $mc->event('transaction', $data);

        $this->onBusinessEvent($event);
    }

    private function getOrderData(CheckoutOrderPlacedEvent $event)
    {
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
            /** @var MappEvent $mevent */
            foreach ($mappevents as $mevent) {
                $data['messageId'] = $mevent->getMessageId();
            }
        }

        return $data;
    }

    private function getOrderItems(OrderEntity $orderEntity, Context $context)
    {
        $items = array();
        foreach ($orderEntity->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE)
                continue;

            /** @var ProductEntity|null */
            $product = $this->productRepository->search(new Criteria([$lineItem->getProductId()]), $context)->first();

            $item['quantity'] = $lineItem->getQuantity();
            $item['unitPrice'] = strval($lineItem->getUnitPrice());

            if (!is_null($product)) {
                $productName = $product->getName();
                if ($productName === null) {
                    $productName = $lineItem->getLabel();
                }

                $item['productId'] = $product->getProductNumber();
                $item['name'] = $productName;
                $item['description'] = $product->getDescription();

                if (!is_null($product->getManufacturerId())) {
                    /** @var ProductManufacturerEntity|null */
                    $productManufacturer = $this->productManufacturerRepository->search(new Criteria([$product->getManufacturerId()]), $context)->first();
                    $item['manufacturerName'] = $productManufacturer->getName();
                }
            }

            array_push($items, $item);
        }
        return $items;
    }

    private function getMappEvents(Event $event, Context $context): MappEventCollection
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

    public function onBusinessEvent(Event $innerEvent)
    {
        if (!$this->shouldTriggerDefaultLogic($innerEvent)) {
            return;
        }

        $userEmailFromEvent = $this->getUserEmailFromEvent($innerEvent);

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
            $eventData = $this->extractEventData($innerEvent);
            $eventDataToBeSent = $this->getDataToBeSentForEvent($eventData, $innerEvent);
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
        }

        /** @var MappEvent $mevent */
        foreach ($mappevents as $mevent) {
            if ($mevent->getMessageId() > 0) {
                $type = 'email';
                $data['messageId'] = $mevent->getMessageId();
            }
            $mc->event($type, $data);
        }

    }

    private function shouldTriggerDefaultLogic(Event $innerEvent)
    {
        return !($innerEvent instanceof EntityWrittenEvent || $innerEvent instanceof CheckoutOrderPlacedEvent);
    }

    private function getUserEmailFromEvent(Event $innerEvent)
    {
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
        } else {
            return "unknown";
        }
    }

    private function getDataToBeSentForEvent(array $eventData, Event $innerEvent)
    {
        $result = array();
        if (array_key_exists(get_class($innerEvent), self::$fieldsForEvents)) {
            $fieldsToBeExtracted = self::$fieldsForEvents[get_class($innerEvent)];
            foreach ($fieldsToBeExtracted as $key => $value) {
                $result[$value] = $eventData[$value];
            }
        } else {
            foreach ($eventData as $key => $val) {
                if (substr_count($key, '.') < 2)
                    $result[$key] = $val;
            }
        }
        return $result;
    }

    private function extractEventData(Event $innerEvent)
    {
        $result = array();
        try {
            $normalizer = new ObjectNormalizer();
            $encoder = new JsonEncoder();

            $serializer = new Serializer([$normalizer], [$encoder]);

            $ser = $serializer->serialize($innerEvent, 'json', [AbstractNormalizer::IGNORED_ATTRIBUTES => ['language',
                'translations', 'registrationTitle', 'registrationIntroduction', 'registrationOnlyCompanyRegistration',
                'registrationSeoMetaDescription']]);

            $arrayData = $serializer->normalize($innerEvent, 'json', [AbstractNormalizer::IGNORED_ATTRIBUTES => ['language',
                'translations', 'registrationTitle', 'registrationIntroduction', 'registrationOnlyCompanyRegistration',
                'registrationSeoMetaDescription']]);

            foreach (array_keys($innerEvent->getAvailableData()->toArray()) as $key) {
                $result[$key] = $arrayData[$key];
            }

            $result = $this->flattenArray($result);
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
