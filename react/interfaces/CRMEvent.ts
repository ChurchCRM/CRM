interface CRMEvent {
    Desc: string,
    End: string,
    Id: number,
    InActive: boolean,
    Start: string,
    Text: string,
    Type: number,
    Title: string,
    PinnedCalendars?: number[]
  }
export default CRMEvent