/**
 * Kiosk TypeScript Type Definitions
 */

export interface ClassMember {
  displayName: string;
  firstName: string;
  classRole: string;
  personId: number;
  status: number;
  gender: number;
  hasPhoto: boolean;
  age: number | null;
  birthdayThisMonth: boolean;
  birthdayUpcoming: boolean;
  birthdayRecent: boolean;
  birthdayToday: boolean;
  birthDay: number | null;
  birthMonth: number | null;
  familyId: number | null;
}

export interface PersonApiData {
  Id: number;
  FirstName: string;
  LastName: string;
  RoleName: string;
  status: number;
  Gender: number;
  hasPhoto: boolean;
  age: number | null;
  birthdayThisMonth: boolean;
  birthdayUpcoming: boolean;
  birthdayRecent: boolean;
  birthdayToday: boolean;
  birthDay: number | null;
  birthMonth: number | null;
  familyId: number | null;
}

export interface ActiveClassMembersResponse {
  People: PersonApiData[];
  GroupName?: string;
  notificationsEnabled?: boolean;
}

export interface HeartbeatResponse {
  Assignment: string;
  Commands?: string;
  Name: string;
  Accepted: boolean;
}

export interface KioskAssignment {
  AssignmentType: number;
  EventId: number;
  Event: {
    Title: string;
    Start: string;
    End: string;
    GroupId?: number;
  };
}

export interface AjaxOptions {
  method?: string;
  path: string;
  data?: string;
  url?: string;
  dataType?: string;
  contentType?: string;
}

export interface KioskJSOM {
  notificationsEnabled: boolean;
  kioskEventLoop?: ReturnType<typeof setInterval>;
  escapeHtml: (text: string | null | undefined) => string;
  APIRequest: (options: AjaxOptions) => JQuery.jqXHR;
  getPhotoUrl: (personId: number) => string;
  renderClassMember: (classMember: ClassMember) => void;
  updateMemberCounts: () => void;
  renderBirthdaySection: (birthdayPeople: ClassMember[]) => void;
  renderBirthdayCard: (person: ClassMember, monthNames: string[], cardType: string) => JQuery;
  updateActiveClassMembers: () => void;
  renderNoMembersMessage: () => string;
  renderErrorMessage: (message: string, statusCode?: number) => string;
  heartbeat: () => void;
  renderCountdown: (eventStart: moment.Moment, eventTitle: string) => string;
  renderEventEnded: (eventTitle: string) => string;
  startCountdown: (eventStart: moment.Moment) => void;
  renderStatusCard: (
    statusType: string,
    iconClass: string,
    title: string,
    kioskName: string,
    bodyContent: string | null,
  ) => string;
  checkInPerson: (personId: number) => void;
  checkOutPerson: (personId: number) => void;
  checkOutAll: () => void;
  alertAll: () => void;
  setCheckedOut: (personId: number) => void;
  setCheckedIn: (personId: number) => void;
  triggerNotification: (personId: number) => void;
  showKioskNotification: (message: string, type: string) => void;
  enterFullScreen: () => void;
  exitFullScreen: () => void;
  displayPersonInfo: (personId: number) => void;
  startEventLoop: () => void;
  stopEventLoop: () => void;
}
