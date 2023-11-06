interface CRMEvent {
  Desc?: string;
  End?: Date;
  Id: number;
  InActive?: boolean;
  Start?: Date;
  Text?: string;
  Type?: number;
  Title?: string;
  LocationId?: number;
  PrimaryContactPersonId?: number;
  SecondaryContactPersonId?: number;
  URL?: string;
  PinnedCalendars?: number[];
}
export default CRMEvent;
