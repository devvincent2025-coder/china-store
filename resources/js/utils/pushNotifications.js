import { router } from '@inertiajs/react';

// PushManager needs the VAPID public key as a raw Uint8Array, not the base64url string Laravel
// generated it as — this is the standard conversion recipe from the Web Push spec.
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);

    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
}

export function isPushSupported() {
    return typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window;
}

export async function getPushSubscriptionStatus() {
    if (!isPushSupported()) return 'unsupported';
    if (Notification.permission === 'denied') return 'denied';

    const registration = await navigator.serviceWorker.getRegistration();
    const subscription = await registration?.pushManager.getSubscription();

    return subscription ? 'subscribed' : 'unsubscribed';
}

/**
 * Registers the service worker (if not already), asks for Notification permission, subscribes via
 * PushManager, and posts the subscription to the backend (see PushSubscriptionController::store()).
 */
export async function subscribeToPush() {
    if (!isPushSupported()) {
        throw new Error('Push notifications are not supported in this browser.');
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        throw new Error('Notification permission was not granted.');
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    await navigator.serviceWorker.ready;

    const vapidPublicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;
    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });

    router.post(route('push-subscriptions.store'), subscription.toJSON(), { preserveScroll: true, preserveState: true });

    return subscription;
}

export async function unsubscribeFromPush() {
    const registration = await navigator.serviceWorker.getRegistration();
    const subscription = await registration?.pushManager.getSubscription();

    if (!subscription) return;

    const endpoint = subscription.endpoint;
    await subscription.unsubscribe();

    router.delete(route('push-subscriptions.destroy'), {
        data: { endpoint },
        preserveScroll: true,
        preserveState: true,
    });
}
