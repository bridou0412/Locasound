import './index.scss';
import moment from 'moment';
import queryClient from '@/globals/queryClient';
import { DATE_DB_FORMAT } from '@/globals/constants';
import apiEvents from '@/stores/api/events';
import { confirm } from '@/utils/alert';
import EventDetails from '@/themes/default/modals/EventDetails';
import Page from '@/themes/default/components/Page';
import CriticalError from '@/themes/default/components/CriticalError';
import Timeline from '@/themes/default/components/Timeline';
import CalendarHeader from './components/Header';
import CalendarCaption from './components/Caption';
import { formatEvent, getDefaultPeriod } from './_utils';
import { Group } from '@/stores/api/groups';

const ONE_DAY = 1000 * 3600 * 24;

// @vue/component
export default {
    name: 'Calendar',
    data() {
        const parkFilter = this.$route.query.park;
        const { start, end } = getDefaultPeriod();

        return {
            hasCriticalError: false,
            isLoading: false,
            isSaving: false,
            isDeleting: false,
            isOverItem: false,
            fetchStart: moment(start).subtract(8, 'days').startOf('day'),
            fetchEnd: moment(end).add(1, 'months').endOf('month'),
            isModalOpened: false,
            filterMissingMaterial: false,
            parkId: parkFilter ? Number.parseInt(parkFilter, 10) : null,
            events: [],
        };
    },
    computed: {
        helpText() {
            const { $t: __, isOverItem } = this;
            return isOverItem
                ? __('page.calendar.help-timeline-event-operations')
                : __('page.calendar.help');
        },

        isVisitor() {
            return this.$store.getters['auth/is'](Group.VISITOR);
        },

        timelineOptions() {
            const { isVisitor } = this;
            const { start, end } = getDefaultPeriod();

            return {
                start,
                end,
                selectable: !isVisitor,
                zoomMin: ONE_DAY * 7,
                zoomMax: ONE_DAY * 6 * 30,
            };
        },

        formattedEvents() {
            const { $t: __, $store: { state: { settings } }, events } = this;
            const { showLocation = true, showBorrower = false } = settings.calendar.event;
            return events.map((event) => formatEvent(event, __, { showBorrower, showLocation }));
        },

        filteredEvents() {
            const { formattedEvents, parkId, filterMissingMaterial } = this;
            let events = [...formattedEvents];

            if (parkId) {
                events = events.filter(({ parks: eventParks }) => (
                    eventParks === null || eventParks?.includes(this.parkId)
                ));
            }

            if (filterMissingMaterial) {
                events = events.filter(({ hasMissingMaterials }) => !!hasMissingMaterials);
            }

            return events;
        },
    },
    mounted() {
        this.getEventsData();
    },
    methods: {
        // ------------------------------------------------------
        // -
        // -    Handlers
        // -
        // ------------------------------------------------------

        handleRefresh() {
            this.getEventsData();
        },

        handleSetCenterDate(date) {
            this.$refs.calendarTimeline.moveTo(date);
        },

        handleFilterMissingMaterial(filterMissingMaterial) {
            this.filterMissingMaterial = filterMissingMaterial;
        },

        handleFilterByPark(parkId) {
            this.parkId = parkId === '' ? null : Number.parseInt(parkId, 10);
        },

        handleRangeChanged(newPeriod) {
            const dates = Object.fromEntries(['start', 'end'].map(
                (type) => [type, moment(newPeriod[type])],
            ));

            localStorage.setItem('calendarStart', dates.start.format('YYYY-MM-DD HH:mm:ss'));
            localStorage.setItem('calendarEnd', dates.end.format('YYYY-MM-DD HH:mm:ss'));
            this.$refs.Header.changePeriod(dates);

            let needFetch = false;
            if (this.fetchStart.isAfter(dates.start)) {
                this.fetchStart = moment(dates.start).subtract(8, 'days').startOf('day');
                needFetch = true;
            }

            if (this.fetchEnd.isBefore(dates.end)) {
                this.fetchEnd = moment(dates.end).add(1, 'months').endOf('month');
                needFetch = true;
            }

            if (needFetch) {
                this.getEventsData();
            }
        },

        //
        // - Handlers pour les items.
        //

        handleItemOver() {
            this.isOverItem = true;
        },

        handleItemOut() {
            this.isOverItem = false;
        },

        handleItemDoubleClick(e) {
            const { isModalOpened, isVisitor, handleUpdateEvent, handleDuplicateEvent, getEventsData } = this;

            // - On ??vite le double-call ?? cause d'un bug qui trigger l'event en double.
            // - @see visjs bug here: https://github.com/visjs/vis-timeline/issues/301)
            if (isModalOpened) {
                return;
            }

            const eventId = e.item;
            if (eventId) {
                this.$modal.show(
                    EventDetails,
                    {
                        eventId,
                        onUpdateEvent: handleUpdateEvent,
                        onDuplicateEvent: handleDuplicateEvent,
                    },
                    undefined,
                    { 'before-close': () => { getEventsData(); } },
                );
                this.isModalOpened = true;
                return;
            }

            if (isVisitor) {
                return;
            }

            const atDate = moment(e.time).startOf('day').format('YYYY-MM-DD');
            this.$router.push({ name: 'add-event', query: { atDate } });
        },

        async handleItemMoved(item, callback) {
            const { isVisitor } = this;
            if (isVisitor) {
                return;
            }

            const itemEnd = moment(item.end);
            if (itemEnd.hour() === 0) {
                itemEnd.subtract(1, 'day').endOf('day');
            }
            const data = {
                start_date: moment(item.start).format(DATE_DB_FORMAT),
                end_date: itemEnd.format(DATE_DB_FORMAT),
            };

            const { $t: __, getEventsData } = this;
            this.isSaving = true;

            try {
                await apiEvents.update(item.id, data);

                // - Permet de placer l'??l??ment ?? sa nouvelle place sur la timeline
                callback(item);

                this.$toasted.success(__('page.calendar.event-saved'));
                queryClient.invalidateQueries('materials-while-event');
                getEventsData();
            } catch {
                this.$toasted.error(__('errors.unexpected-while-saving'));

                // - Permet d'annuler le d??placement de l'??l??ment sur la timeline
                callback(null);
            } finally {
                this.isSaving = false;
            }
        },

        async handleItemRemove(item, callback) {
            const { isVisitor } = this;
            if (isVisitor || item.isConfirmed) {
                return;
            }

            const { $t: __, getEventsData } = this;

            const { value: isConfirmed } = await confirm({
                type: 'warning',
                text: __('@event.confirm-delete'),
                confirmButtonText: __('yes-delete'),
            });
            if (!isConfirmed) {
                // - Permet d'annuler la suppression de l'??l??ment sur la timeline
                callback(null);
                return;
            }

            this.isDeleting = true;

            try {
                await apiEvents.remove(item.id);

                // - Permet de supprimer l'??l??ment de la timeline
                callback(item);

                this.$toasted.success(__('page.calendar.event-deleted'));
                queryClient.invalidateQueries('materials-while-event');
                getEventsData();
            } catch {
                this.$toasted.error(__('errors.unexpected-while-saving'));
                callback(null);
            } finally {
                this.isDeleting = false;
            }
        },

        handleUpdateEvent(newEventData) {
            queryClient.invalidateQueries('materials-while-event');
            const toUpdateIndex = this.events.findIndex(
                (event) => event.id === newEventData.id,
            );
            if (toUpdateIndex >= 0) {
                this.$set(this.events, toUpdateIndex, newEventData);
            }
        },

        handleDuplicateEvent(newEvent) {
            const { start_date: startDate } = newEvent;
            const date = moment(startDate).toDate();
            this.$refs.calendarTimeline.moveTo(date);
        },

        // ------------------------------------------------------
        // -
        // -    M??thodes internes
        // -
        // ------------------------------------------------------

        async getEventsData() {
            this.isLoading = true;
            this.isModalOpened = false;

            const params = {
                start: this.fetchStart.format('YYYY-MM-DD HH:mm:ss'),
                end: this.fetchEnd.format('YYYY-MM-DD HH:mm:ss'),
            };

            try {
                this.events = (await apiEvents.all(params)).data;
            } catch {
                this.hasCriticalError = true;
            } finally {
                this.isLoading = false;
            }
        },
    },
    render() {
        const {
            $t: __,
            isLoading,
            isSaving,
            isDeleting,
            hasCriticalError,
            helpText,
            filteredEvents,
            timelineOptions,
            handleRefresh,
            handleItemDoubleClick,
            handleRangeChanged,
            handleFilterByPark,
            handleSetCenterDate,
            handleFilterMissingMaterial,
            handleItemOver,
            handleItemOut,
            handleItemMoved,
            handleItemRemove,
        } = this;

        if (hasCriticalError) {
            return (
                <Page name="calendar" title={__('page.calendar.title')}>
                    <CriticalError />
                </Page>
            );
        }

        return (
            <Page name="calendar" title={__('page.calendar.title')}>
                <div class="Calendar">
                    <CalendarHeader
                        ref="Header"
                        isLoading={isLoading || isSaving || isDeleting}
                        onRefresh={handleRefresh}
                        onSetCenterDate={handleSetCenterDate}
                        onFilterMissingMaterials={handleFilterMissingMaterial}
                        onFilterByPark={handleFilterByPark}
                    />
                    <Timeline
                        ref="calendarTimeline"
                        class="Calendar__timeline"
                        items={filteredEvents}
                        options={timelineOptions}
                        onItemOver={handleItemOver}
                        onItemOut={handleItemOut}
                        onItemMoved={handleItemMoved}
                        onItemRemove={handleItemRemove}
                        onDoubleClick={handleItemDoubleClick}
                        onRangeChanged={handleRangeChanged}
                    />
                    <div class="Calendar__footer">
                        <p class="Calendar__footer__help">{helpText}</p>
                        <CalendarCaption />
                    </div>
                </div>
            </Page>
        );
    },
};
