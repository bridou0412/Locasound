import moment from 'moment';

const formatTimelineEvent = (dataEvent) => {
  const {
    start_date: rawStartDate,
    end_date: rawEndDate,
    is_confirmed: isConfirmed,
    has_missing_materials: hasMissingMaterials,
  } = dataEvent;

  const now = moment();
  const startDate = moment(rawStartDate);
  const endDate = moment(rawEndDate);
  const isPast = endDate.isBefore(now, 'day');
  const isCurrent = now.isBetween(startDate, endDate, 'day', '[]');

  return {
    ...dataEvent,
    startDate,
    endDate,
    isConfirmed,
    isPast,
    isCurrent,
    hasMissingMaterials,
  };
};

export default formatTimelineEvent;