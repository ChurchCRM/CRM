interface CRMEvent {
    Desc: string,
    End: string,
    Id: number,
    InActive: boolean,
    Start: string,
    Text: string,
    Type: number,
    Title: string,
    TypeName: string,
    LocationId: number,
    PrimaryContactPersonId: number,
    SecondaryContactPersonId: number,
    URL: string,
    PinnedCalendars?: number[]
  }
export default CRMEvent