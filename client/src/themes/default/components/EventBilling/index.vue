<template>
    <section class="EventBilling">
        <DisplayBill v-if="hasBill && !displayCreateBill && !loading" :data="lastBill" />
        <div v-if="hasBill && userCanEdit" class="EventBilling__regenerate">
            <p class="EventBilling__regenerate__text">
                {{ $t('regenerate-bill-help') }}
            </p>
            <button
                v-if="!displayCreateBill && !loading"
                class="EventBilling__regenerate__button"
                @click="openBillRegeneration"
            >
                <i class="fas fa-sync" />
                {{ $t('click-here-to-regenerate-bill') }}
            </button>
        </div>
        <div v-if="!isBillable" class="EventBilling__not-billable">
            <h3 class="EventBilling__not-billable__title">
                <i class="fas fa-exclamation-triangle" />
                {{ $t('missing-beneficiary') }}
            </h3>
            <p v-if="userCanEdit" class="EventBilling__not-billable__text">
                {{ $t('not-billable-help') }}<br />
                {{ $t('click-edit-to-create-one') }}
            </p>
        </div>
        <div v-if="!hasBill && isBillable">
            <div v-if="userCanEdit && !hasEstimate" class="EventBilling__warning-no-estimate">
                {{ $t('warning-no-estimate-before-billing') }}
            </div>
            <p class="EventBilling__no-bill">
                {{ $t('no-bill-help') }}<br />
                <span v-if="userCanEdit">{{ $t('create-bill-help') }}</span>
                <span v-else>{{ $t('contact-someone-to-create-bill') }}</span>
            </p>
        </div>
        <BillingForm
            v-if="userCanCreateBill"
            :discountRate="discountRate"
            :discountTarget="discountTarget"
            :maxAmount="grandTotal"
            :maxRate="maxDiscountRate"
            :beneficiary="event.beneficiaries[0]"
            :saveLabel="$t('create-bill')"
            :isRegeneration="hasBill"
            @change="handleChangeDiscount"
            @submit="handleCreateBill"
            @cancel="closeBillRegeneration"
            :loading="loading"
        />
        <div v-if="event.bills && event.bills.length > 1 && !displayCreateBill && !loading">
            <h3 class="EventBilling__list-title">{{ $t('previous-bills') }}</h3>
            <ul class="EventBilling__list">
                <li
                    v-for="(bill, index) in event.bills"
                    :key="bill.id"
                    class="EventBilling__list__item"
                    :class="{ 'EventBilling__list__item--current': index === 0 }"
                >
                    <DisplayBill v-if="bill && !displayCreateBill && !loading" :data="bill" />
                </li>
            </ul>
        </div>
    </section>
</template>

<script src="./index.js"></script>
