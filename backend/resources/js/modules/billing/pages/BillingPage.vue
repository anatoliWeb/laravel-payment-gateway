<template>
  <section class="billing-launchpad">
    <header class="billing-launchpad__hero c-card">
      <div class="billing-launchpad__copy">
        <p class="billing-launchpad__eyebrow">Billing Control Center</p>
        <h2>Permission-aware billing launchpad</h2>
        <p>
          Billing operations and analytics live in the Angular dashboard. This Vue Admin page keeps the surface thin and gives
          admins permission-aware shortcuts to the real billing areas.
        </p>
      </div>

      <dl class="billing-launchpad__context">
        <div>
          <dt>Angular dashboard</dt>
          <dd>{{ dashboardBaseUrl }}</dd>
        </div>
        <div>
          <dt>Access level</dt>
          <dd>{{ accessSummary }}</dd>
        </div>
      </dl>
    </header>

    <section class="billing-launchpad__grid">
      <article
        v-for="card in visibleCards"
        :key="card.id"
        class="billing-card c-card"
        :data-testid="`billing-launchpad-card-${card.id}`"
      >
        <div class="billing-card__header">
          <p class="billing-card__eyebrow">{{ card.eyebrow }}</p>
          <span class="billing-card__badge" :class="`billing-card__badge--${card.badgeTone}`">{{ card.badge }}</span>
        </div>

        <h3>{{ card.title }}</h3>
        <p>{{ card.description }}</p>

        <div class="billing-card__footer">
          <span class="billing-card__note">{{ card.note }}</span>
          <a
            class="billing-card__link"
            :href="card.href"
            target="_blank"
            rel="noopener noreferrer"
            :data-testid="`billing-launchpad-link-${card.id}`"
          >
            {{ card.cta }}
          </a>
        </div>
      </article>
    </section>

    <article class="billing-launchpad__footer c-card">
      <h3>Where the real billing surfaces live</h3>
      <div v-if="visibleCards.length > 0" class="billing-launchpad__notes">
        <p>
          Operational billing management, billing reports, demo flows, customer billing, and company or seller views are all
          implemented in the Angular dashboard so this page stays a launcher rather than a duplicate app.
        </p>
        <p>
          Backend permissions remain authoritative. The Vue page only decides which shortcuts are visible.
        </p>
      </div>
      <div v-else class="billing-launchpad__notes billing-launchpad__notes--empty" data-testid="billing-launchpad-empty">
        <p>No billing shortcuts are visible for the current permissions context.</p>
      </div>
    </article>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';

import { buildDashboardUrl, getDashboardBaseUrl } from '../../../shared/config/external-links';
import { BILLING_ADMIN_ACCESS_PERMISSIONS } from '../../../shared/constants/billing';
import { useAuthStore } from '../../../stores/auth.store';

type BadgeTone = 'positive' | 'neutral' | 'accent';

type LaunchpadCard = {
  id: string;
  eyebrow: string;
  title: string;
  description: string;
  badge: string;
  badgeTone: BadgeTone;
  cta: string;
  href: string;
  note: string;
  visible: () => boolean;
};

const authStore = useAuthStore();
const dashboardBaseUrl = getDashboardBaseUrl();

const operationalBillingPermissions = [
  'billing.payments.view_any',
  'billing.subscriptions.view_any',
  'billing.wallets.view_any',
  'billing.invoices.view_any',
  'billing.webhooks.view_any',
  'billing.idempotency.view_any',
  'billing.provider_accounts.view_any',
  'billing.restrictions.view_any',
  'billing.overrides.view_any',
];

const hasOperationalAccess = computed(() => authStore.hasAnyPermission(operationalBillingPermissions));
const hasReportsAccess = computed(() => authStore.hasPermission('billing.reports.view'));
const hasAnyBillingAccess = computed(() => authStore.hasAnyPermission(BILLING_ADMIN_ACCESS_PERMISSIONS));

const launchpadCards: LaunchpadCard[] = [
  {
    id: 'admin',
    eyebrow: 'Admin operations',
    title: 'Admin Billing Management',
    description:
      'Open the Angular operational dashboard for payments, invoices, wallets, webhook deliveries, idempotency, provider accounts, restrictions, and overrides.',
    badge: 'Operational',
    badgeTone: 'positive',
    cta: 'Open admin billing',
    href: buildDashboardUrl('/admin/billing'),
    note: 'Angular `/admin/billing`',
    visible: () => hasOperationalAccess.value,
  },
  {
    id: 'reports',
    eyebrow: 'Analytics',
    title: 'Billing Reports',
    description:
      'Open the Angular reports dashboard for authoritative revenue summaries, payment states, subscription metrics, and scoped filters.',
    badge: 'Reports',
    badgeTone: 'accent',
    cta: 'Open reports dashboard',
    href: buildDashboardUrl('/admin/billing/reports'),
    note: 'Backend aggregate view',
    visible: () => hasReportsAccess.value,
  },
  {
    id: 'demo',
    eyebrow: 'Portfolio walkthrough',
    title: 'Billing Demo Flows',
    description:
      'Open the Angular demo surface for plan purchase, wallet top-up, invoice payment, failure simulation, and webhook history.',
    badge: 'Demo',
    badgeTone: 'neutral',
    cta: 'Open demo flows',
    href: buildDashboardUrl('/billing/demo'),
    note: 'Simulator-backed walkthrough',
    visible: () => hasAnyBillingAccess.value,
  },
  {
    id: 'portal',
    eyebrow: 'Customer view',
    title: 'User Billing Portal',
    description:
      'Inspect the customer-facing subscription, usage, wallet, payment method, and payment history surface in the Angular billing module.',
    badge: 'Portal',
    badgeTone: 'neutral',
    cta: 'Open user portal',
    href: buildDashboardUrl('/billing'),
    note: 'Customer-scoped billing view',
    visible: () => hasAnyBillingAccess.value,
  },
  {
    id: 'company',
    eyebrow: 'Ownership view',
    title: 'Company Billing View',
    description:
      'Inspect the company-scoped billing surface for invoices, payments, and summarized usage without duplicating query logic in Vue.',
    badge: 'Company',
    badgeTone: 'neutral',
    cta: 'Open company view',
    href: buildDashboardUrl('/billing/company'),
    note: 'Company-scoped Angular route',
    visible: () => hasAnyBillingAccess.value,
  },
  {
    id: 'seller',
    eyebrow: 'Ownership view',
    title: 'Seller Billing View',
    description:
      'Inspect the seller-scoped billing surface for payments, invoices, and revenue context in the Angular module.',
    badge: 'Seller',
    badgeTone: 'neutral',
    cta: 'Open seller view',
    href: buildDashboardUrl('/billing/seller'),
    note: 'Seller-scoped Angular route',
    visible: () => hasAnyBillingAccess.value,
  },
  {
    id: 'docs',
    eyebrow: 'Reference',
    title: 'API Documentation',
    description: 'Open the billing API docs to review the backend contract that powers both the Vue and Angular surfaces.',
    badge: 'Docs',
    badgeTone: 'accent',
    cta: 'Open docs',
    href: '/docs/api/portal',
    note: 'Backend contract reference',
    visible: () => authStore.hasPermission('api.docs.view'),
  },
];

const visibleCards = computed(() => launchpadCards.filter((card) => card.visible()));

const accessSummary = computed(() => {
  if (hasOperationalAccess.value) {
    return 'Operational billing access';
  }

  if (hasReportsAccess.value) {
    return 'Reports access';
  }

  if (hasAnyBillingAccess.value) {
    return 'Billing access';
  }

  return 'Permission required';
});
</script>

<style scoped>
.billing-launchpad {
  display: grid;
  gap: 16px;
}

.billing-launchpad__hero,
.billing-launchpad__footer {
  margin-top: 0;
}

.billing-launchpad__hero {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(240px, 0.8fr);
  gap: 16px;
  align-items: start;
}

.billing-launchpad__copy h2,
.billing-launchpad__footer h3,
.billing-card h3 {
  margin: 0;
  color: #f8fafc;
}

.billing-launchpad__eyebrow,
.billing-card__eyebrow {
  margin: 0 0 6px;
  color: #38bdf8;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.billing-launchpad__copy p,
.billing-launchpad__footer p,
.billing-card p {
  margin: 0;
  color: #94a3b8;
  font-size: 13px;
  line-height: 1.55;
}

.billing-launchpad__copy p {
  margin-top: 8px;
  max-width: 68ch;
}

.billing-launchpad__context {
  display: grid;
  gap: 10px;
}

.billing-launchpad__context div {
  border: 1px solid rgba(71, 85, 105, 0.45);
  border-radius: 8px;
  background: rgba(15, 23, 42, 0.5);
  padding: 12px;
}

.billing-launchpad__context dt {
  color: #94a3b8;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.billing-launchpad__context dd {
  margin: 6px 0 0;
  color: #e2e8f0;
  font-size: 13px;
  font-weight: 600;
  word-break: break-all;
}

.billing-launchpad__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.billing-card {
  margin-top: 0;
  display: grid;
  gap: 12px;
}

.billing-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.billing-card__badge {
  border-radius: 999px;
  padding: 4px 8px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.billing-card__badge--positive {
  color: #dcfce7;
  background: rgba(34, 197, 94, 0.16);
  border: 1px solid rgba(34, 197, 94, 0.26);
}

.billing-card__badge--accent {
  color: #dbeafe;
  background: rgba(59, 130, 246, 0.16);
  border: 1px solid rgba(59, 130, 246, 0.26);
}

.billing-card__badge--neutral {
  color: #e2e8f0;
  background: rgba(148, 163, 184, 0.12);
  border: 1px solid rgba(148, 163, 184, 0.2);
}

.billing-card__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.billing-card__note {
  color: #94a3b8;
  font-size: 11px;
}

.billing-card__link {
  border: 1px solid rgba(59, 130, 246, 0.45);
  border-radius: 8px;
  background: rgba(59, 130, 246, 0.12);
  color: #bfdbfe;
  padding: 8px 10px;
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}

.billing-card__link:hover {
  background: rgba(59, 130, 246, 0.18);
  border-color: rgba(96, 165, 250, 0.7);
}

.billing-card__link:active {
  transform: translateY(1px);
}

.billing-launchpad__footer {
  display: grid;
  gap: 10px;
}

.billing-launchpad__notes {
  display: grid;
  gap: 8px;
}

.billing-launchpad__notes--empty {
  padding: 2px 0;
}

@media (max-width: 1080px) {
  .billing-launchpad__grid,
  .billing-launchpad__hero {
    grid-template-columns: 1fr;
  }
}
</style>
