export interface Entity {
    id?: string;
    mode?: "add" | "remove";
    type: "item" | "property";
    labels?: {
        [key in string]?: {
            language: key;
            value: string;
        };
    };
    claims?: {
        [key in string]: Array<{
            mainsnak: {
                snaktype: string;
                property: key;
                datatype: string;
                datavalue: {
                    value: string;
                    type: string;
                };
            };
            type: string;
            rank: string;
        }>;
    };
    descriptions?: {
        [key in string]?: {
            language: key;
            value: string;
        };
    };
}
